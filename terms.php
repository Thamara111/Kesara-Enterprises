<?php
/**
 * Wholesale Terms and Conditions Page
 */
require_once __DIR__ . "/database/connection.php";

$page_meta = [
    'title' => 'Wholesale Terms & Conditions | Kesara Enterprises',
    'description' => 'Wholesale Terms and Conditions for Kesara Enterprises B2B wholesale platform.',
];

require_once __DIR__ . "/layouts/head.php";
require_once __DIR__ . "/layouts/header.php";
?>

<main class="bg-gray-50 min-h-screen py-12 px-6">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-12">
        
        <!-- Header -->
        <div class="border-b border-gray-100 pb-8 mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Wholesale Terms and Conditions</h1>
            <p class="text-gray-500 mt-2 font-medium">Kesara Enterprises (Pvt) Ltd <br/> Maharagama, Sri Lanka</p>
            <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase mt-4">Last updated: January 2026</p>
        </div>

        <div class="text-sm text-gray-600 space-y-6 [&>h2]:text-gray-900 [&>h2]:font-semibold [&>h2]:text-xl [&>h2]:mt-10 [&>h2]:mb-5 [&>p]:leading-loose [&>h2~p]:pl-4">
            <p>
                These Wholesale Terms and Conditions ("Terms") govern all purchases made through the Kesara Enterprises B2B wholesale platform ("Platform") by registered wholesale buyers ("Buyer", "you"). By creating an account or placing an order, you agree to be bound by these Terms.
            </p>

            <h2>1. Eligibility and Account Approval</h2>
            <p>1.1 The Platform is available exclusively to registered business buyers, including retail shop owners, boutique operators, and distributors purchasing for resale.</p>
            <p>1.2 All new accounts are subject to manual review and approval by Kesara Enterprises before wholesale pricing and ordering privileges are activated.</p>
            <p>1.3 Buyers must provide a valid Business Registration (BR) number and, where applicable, a VAT registration number during sign-up. Kesara Enterprises reserves the right to request supporting documentation to verify business legitimacy.</p>
            <p>1.4 Kesara Enterprises reserves the right to reject, suspend, or terminate any account at its sole discretion, including accounts found to be misrepresenting business details or engaging in fraudulent activity.</p>
            <p>1.5 Account credentials are for the sole use of the approved business and must not be shared or transferred without prior written consent.</p>

            <h2>2. Minimum Order Quantity (MOQ)</h2>
            <p>2.1 All products listed on the Platform are subject to a Minimum Order Quantity per item or per style, as displayed on the relevant product page.</p>
            <p>2.2 Orders that do not meet the specified MOQ will not be accepted for checkout. The cart will display a validation message indicating the shortfall.</p>
            <p>2.3 MOQ thresholds may vary by product category and are subject to change without prior notice. The MOQ shown at the time of order confirmation shall apply.</p>
            <p>2.4 Mixed quantities across sizes or colours within the same style may count toward the overall MOQ for that style, unless stated otherwise on the product listing.</p>

            <h2>3. Pricing and Tiered Discounts</h2>
            <p>3.1 All prices displayed on the Platform are quoted in Sri Lankan Rupees (LKR) and are exclusive of applicable taxes unless stated otherwise.</p>
            <p>3.2 Kesara Enterprises offers quantity-based tiered pricing. Unit prices decrease automatically as order quantities increase, in accordance with the pricing tiers published on each product page.</p>
            <p>3.3 Tiered pricing is calculated per line item at checkout based on the confirmed order quantity and is subject to change without notice.</p>
            <p>3.4 Prices displayed at the time of order confirmation are final for that order and will not be affected by subsequent price changes.</p>
            <p>3.5 Kesara Enterprises reserves the right to correct pricing errors, including those caused by system or technical faults, at any time prior to order dispatch.</p>

            <h2>4. Order Placement and Confirmation</h2>
            <p>4.1 An order is considered submitted once the Buyer completes checkout and receives an on-screen order confirmation.</p>
            <p>4.2 All orders are subject to acceptance and stock availability. Kesara Enterprises reserves the right to cancel or adjust any order due to stock shortages, pricing errors, or failure to meet MOQ requirements at the point of final review.</p>
            <p>4.3 Order confirmation does not constitute a guarantee of delivery date. Estimated delivery timelines will be communicated separately.</p>

            <h2>5. Payment Terms</h2>
            <p>5.1 Payments may be made through the payment methods supported on the Platform, including online payment gateways and approved bank transfer options.</p>
            <p>5.2 Orders will only be processed for fulfilment once payment has been verified by Kesara Enterprises' finance team.</p>
            <p>5.3 For Buyers approved for credit terms, invoices are payable within the credit period specified in the Buyer's account agreement. Late payments may result in suspension of ordering privileges.</p>
            <p>5.4 All bank transfer payments must include the correct order reference number to enable verification. Kesara Enterprises is not responsible for delays caused by incorrect payment references.</p>

            <h2>6. Delivery</h2>
            <p>6.1 Kesara Enterprises will arrange delivery of confirmed orders through its own delivery network or approved third-party couriers.</p>
            <p>6.2 Estimated delivery timeframes are provided for guidance only and are not guaranteed. Delays may occur due to order volume, location, or circumstances beyond Kesara Enterprises' control.</p>
            <p>6.3 The Buyer is responsible for ensuring that a suitable representative is available to receive and verify the delivery at the registered business address.</p>
            <p>6.4 Any discrepancies between the delivered goods and the order confirmation must be reported within 48 hours of delivery.</p>

            <h2>7. Returns, Exchanges, and Defective Goods</h2>
            <p>7.1 Due to the wholesale nature of orders, returns and exchanges are only accepted for goods that are defective, damaged in transit, or incorrectly supplied.</p>
            <p>7.2 Claims for defective or incorrect goods must be submitted with supporting photographic evidence within 3 business days of delivery.</p>
            <p>7.3 Approved returns will be resolved by replacement, credit note, or refund at Kesara Enterprises' discretion.</p>
            <p>7.4 Goods that have been used, altered, or damaged after delivery are not eligible for return.</p>

            <h2>8. Cancellations</h2>
            <p>8.1 Orders may be cancelled by the Buyer only before the order has been confirmed for dispatch. Requests after this point will be considered on a case-by-case basis.</p>
            <p>8.2 Kesara Enterprises reserves the right to cancel any order where payment verification fails, stock becomes unavailable, or the order is found to violate these Terms.</p>

            <h2>9. Product Information</h2>
            <p>9.1 Kesara Enterprises makes reasonable efforts to ensure product descriptions, images, and specifications on the Platform are accurate. Minor variations in colour, fabric, or fit may occur due to manufacturing processes.</p>
            <p>9.2 Product availability is subject to change without prior notice.</p>

            <h2>10. Intellectual Property</h2>
            <p>10.1 All content on the Platform, including product images, descriptions, logos, and branding, is the property of Kesara Enterprises and may not be reproduced, distributed, or used for commercial purposes without prior written consent.</p>

            <h2>11. Limitation of Liability</h2>
            <p>11.1 Kesara Enterprises shall not be liable for any indirect, incidental, or consequential loss arising from the use of the Platform or delays in delivery.</p>
            <p>11.2 Kesara Enterprises' total liability in respect of any order shall not exceed the total value of that order.</p>

            <h2>12. Amendments</h2>
            <p>12.1 Kesara Enterprises reserves the right to amend these Terms at any time. Continued use of the Platform after changes are published constitutes acceptance of the revised Terms.</p>

            <h2>13. Governing Law</h2>
            <p>13.1 These Terms shall be governed by and construed in accordance with the laws of the Democratic Socialist Republic of Sri Lanka.</p>

            <h2>14. Contact Us</h2>
            <p>For questions regarding these Terms, please contact:</p>
            <p>
                <strong>Kesara Enterprises (Pvt) Ltd</strong><br>
                Maharagama, Sri Lanka<br>
                Email: sales@kesara.lk<br>
                Phone: +94 11 234 5678
            </p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . "/layouts/footer.php"; ?>
