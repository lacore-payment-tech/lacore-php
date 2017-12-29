<?php
namespace Lacore\Tests;


use Lacore\Settings;
use Lacore\Tests\Fixtures;
use Lacore\Resources\User;
use Lacore\Resources\Application;
use Lacore\Resources\Identity;
use Lacore\Resources\Authorization;
use Lacore\Resources\PaymentInstrument;
use Lacore\Resources\BankAccount;
use Lacore\Resources\PaymentCard;
use Lacore\Resources\Transfer;
use Lacore\Resources\Dispute;
use Lacore\Resources\Evidence;
use Lacore\Resources\Webhook;
use Lacore\Resources\Verification;
use Lacore\Resources\Settlement;
use Lacore\Resources\Reversal;
use Lacore\Resources\Processor;
use Lacore\Resources\Merchant;
use Lacore\Resources\InstrumentUpdate;

class ScenariosTest extends \PHPUnit_Framework_TestCase
{
    private $partnerUser;
    private $user;
    private $application;
    private $dummyProcessor;
    private $identity;
    private $merchant;
    private $card;
    private $receiptImage;

    protected function setUp()
    {
        $this->receiptImage = realpath("../../data/receipt.jpg");

        date_default_timezone_set("UTC");

        $username = getenv("LACORE_ADMIN_USERNAME");
        $password = getenv("LACORE_ADMIN_PASSWORD");

        Settings::configure(["username" => $username, "password" => $password ]);

        $this->user = Fixtures::createAdminUser();

        Settings::configure(["username" => $this->user->id, "password" => $this->user->password]);

        $this->application = Fixtures::createApplication($this->user);
        $this->application->processing_enabled = true;
        $this->application->settlement_enabled = true;
        $this->application = $this->application->save();

        $this->dummyProcessor = Fixtures::dummyProcessor($this->application);

        $this->partnerUser = Fixtures::createPartnerUser($this->application);

        Settings::configure(["username" => $this->partnerUser->id, "password" => $this->partnerUser->password]);

        $this->identity = Fixtures::createIdentity();
        $this->bankAccount = Fixtures::createBankAccount($this->identity);
        $this->merchant = Fixtures::provisionMerchant($this->identity);

        $this->card = Fixtures::createCard($this->identity);
    }

    public function testRetrieveIdentity()
    {
        $identity = Identity::retrieve($this->identity->id);
        $this->assertEquals($this->identity->id, $identity->id);
    }

    public function testCreateMerchantUser() {
        $this->markTestSkipped("https://github.com/verygoodgroup/api-spec/issues/333");
        $user = $this->identity->createMerchantUser(new User([["enabled" => true]]));
        self::assertNotNull($user->id);
    }

    public function testCreateBankAccountDirectly() {
        $bankAccount = new BankAccount(
            array(
                "account_type"=> "SAVINGS",
                "name"=> "Fran Lemke",
                "tags"=> array(
                    "Bank Account"=> "Company Account"
                ),
                "country"=> "USA",
                "bank_code"=> "123123123",
                "account_number"=> "123123123",
                "type"=> "BANK_ACCOUNT",
                "identity"=> $this->identity->id
            ));
        $bankAccount = $bankAccount->save();
        self::assertNotNull($bankAccount->id, "Invalid bank account");
    }

    public function testCreatePaymentCardDirectly() {
        $card = new PaymentCard([
            "name" => "Joe Doe",
            "expiration_month" => 12,
            "expiration_year" => 2030,
            "number" => "4111 1111 1111 1111",
            "security_code" => 231,
            "identity"=> $this->identity->id
        ]);
        $card = $card->save();
        self::assertNotNull($card->id, "Invalid card");
    }

    public function testCreateWebhook() {
        $webhook = Fixtures::createWebhook("https://tools.ietf.org/html/rfc2606");
        self::assertNotNull($webhook->id);
    }

    public function testCreateToken() {
        $token = Fixtures::createPaymentToken($this->application, $this->identity->id);
        self::assertNotNull($token->id, "Payment token not created");
    }

    public function testCreateBankAccount() {
        $bankAccount = Fixtures::createBankAccount($this->identity);
        self::assertNotNull($bankAccount->id);
    }

    public function testVerifyIdentity() {
        $verification = $this->identity->verifyOn(new Verification(["processor" => "DUMMY_V1"]));
        self::assertEquals($verification->state, "PENDING");
    }

    public function testDebitTransfer() {
        $transfer = Fixtures::createTransfer([
            "identity" => $this->card->identity,
            "amount" => 2000,
            "source" => $this->card->id,
            "tags" => ["_source" => "php_client"]
        ]);
        self::assertEquals($transfer->state, "PENDING", "Transfer not in pending state");
        return $transfer;
    }

    public function testCaptureAuthorization()
    {
        $authorization = Fixtures::createAuthorization($this->card, 100);
        $authorization = $authorization->capture([
            "capture_amount"=> 100,
            "fee"=> 10
        ]);
        self::assertEquals($authorization->state, "SUCCEEDED", "Capture amount $10 of '" . $this->card->id . "' not succeeded");
    }

    public function testReverseFunds()
    {
        $transfer = $this->testDebitTransfer();
        $transfer = $transfer->reverse(50);
        self::assertEquals($transfer->state, "PENDING", "Reverse not in pending state");
    }

    public function testVoidAuthorization()
    {
        $authorization = Fixtures::createAuthorization($this->card, 100);
        $authorization = $authorization->void(true);
        self::assertTrue($authorization->is_void, "Authorization not void");
    }

    public function testCreateAndFetchInstrumentUpdate()
    {
        $identity = Fixtures::createIdentity();
        Fixtures::createBankAccount($identity);
        $merchant = Fixtures::provisionMerchant($identity);
        $update = $this->card->createUpdate(new InstrumentUpdate(["merchant" => $merchant->id]));
        $this->assertEquals($this->application->id, $update->application);

        $fetchUpdate = InstrumentUpdate::retrieve(PaymentCard::getUpdateUri($this->card->id, $update->id));
        $this->assertEquals($update->id, $fetchUpdate->id);
    }

    public function testGetAllInstrumentUpdates()
    {
        $this->testCreateAndFetchInstrumentUpdate();
        $instrumentUpdatePage = InstrumentUpdate::getPagination($this->card);
        foreach ($instrumentUpdatePage as $indexPage => $instrumentUpdates) {
            foreach ($instrumentUpdates as $index => $instrumentUpdate) { // read the first page
                $this->assertEquals($this->application->id, $instrumentUpdate->application);
            }
        }
    }

    public function testIterateAllTransfers()
    {
        for ($i = 0; $i <= 41; $i++) {
            Fixtures::createTransfer([
                "identity" => $this->card->identity,
                "amount" => Fixtures::$disputeAmount,
                "source" => $this->card->id,
                "tags" => ["_source" => "php_client"]
            ]);
        }

        $transferPage = Transfer::getPagination($this->card);
        foreach ($transferPage as $indexPage => $items) {
            foreach ($items as $index => $transfer) { // read the first page
                $this->assertTrue($transfer->amount > 0);
            }
        }
    }
}
