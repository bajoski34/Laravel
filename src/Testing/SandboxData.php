<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Testing;

/**
 * Flutterwave's official test-mode credentials, for trying flows against the sandbox.
 * They only work with test keys.
 *
 * @see https://developer.flutterwave.com/docs/testing
 */
final class SandboxData
{
    /** Cards that pay successfully: [number, expiry, cvv, pin, otp]. Any OTP passes except the error ones below. */
    public const CARDS = [
        'pin' => ['5531886652142950', '09/32', '564', '3310', '12345'],
        '3ds_mastercard' => ['5438898014560229', '10/31', '564', '3310', '12345'],
        '3ds_visa' => ['4187427415564246', '09/32', '828', '3310', '12345'],
        'noauth_verve' => ['5061460166976054667', '10/29', '564', '3310', null],
        'avs' => ['4556052704172643', '09/32', '899', '3310', '12345'],
        'preauth' => ['5377283645077450', '09/31', '789', '3310', null],
    ];

    /** Cards that fail with a specific error: [number, expiry, cvv, pin, otp]. */
    public const FAILING_CARDS = [
        'do_not_honour' => ['5143010522339965', '08/32', '276', '3310', '12345'],
        'fraudulent' => ['5590131743294314', '11/32', '887', '3310', '12345'],
        'insufficient_funds' => ['5258585922666506', '09/31', '883', '3310', '12345'],
        'incorrect_pin' => ['5399834697894723', '09/31', '883', '3310', '12345'],
    ];

    /** OTPs that fail in the payment modal. */
    public const OTP_WRONG = '5548';

    public const OTP_INSUFFICIENT_FUNDS = '6648';

    /** Access Bank (044) test accounts, 0690000031 to 0690000041. Each resolves and accepts transfers. */
    public const BANK_CODE = '044';

    public const BANK_ACCOUNTS = ['0690000031', '0690000032', '0690000033', '0690000034'];

    /** Transfers to this account fail with "Payout was made to a blacklisted account number". */
    public const BLACKLISTED_ACCOUNT = '0690000036';

    /** Any number with OTP 123456 succeeds; these fail. */
    public const MOBILE_MONEY_OTP = '123456';

    public const FAILING_MOBILE_MONEY = ['233121212121', '233010101011'];

    /** Bills: [biller_code, item_code, customer_id]. Airtime numbers must start with +234 in test mode. */
    public const DSTV = ['BIL119', 'CB140', '0025401100'];

    public const AIRTIME = ['BIL102', 'AT102', '+2347050000000'];

    /** BVN consent: [bvn, first name, last name]. The new-consent flow uses OTP 111111. */
    public const BVN_NEW_CONSENT = ['22222223883', 'Nibby', 'Certifier'];

    public const BVN_EXISTING_CONSENT = ['22123456789', 'Nibby', 'Certifier'];
}
