<?php

namespace App\Providers;

use App\Models\Bank;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\EmailEvent;
use App\Models\Merchant;
use App\Models\MerchantEmailBlast;
use App\Models\MerchantUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductRecurrence;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Observers\BankObserver;
use App\Observers\CheckoutObserver;
use App\Observers\CustomerObserver;
use App\Observers\EmailBlastObserver;
use App\Observers\EmailEventObserver;
use App\Observers\MerchantObserver;
use App\Observers\MerchantUserObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductOptionObserver;
use App\Observers\ProductOptionValueObserver;
use App\Observers\ProductRecurrenceObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\SettingObserver;
use App\Observers\SubscribedProductObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\VoucherObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Checkout::observe(CheckoutObserver::class);
        Customer::observe(CustomerObserver::class);
        EmailEvent::observe(EmailEventObserver::class);
        Merchant::observe(MerchantObserver::class);
        MerchantEmailBlast::observe(EmailBlastObserver::class);
        MerchantUser::observe(MerchantUserObserver::class);
        Order::observe(OrderObserver::class);
        Product::observe(ProductObserver::class);
        ProductOption::observe(ProductOptionObserver::class);
        ProductOptionValue::observe(ProductOptionValueObserver::class);
        ProductRecurrence::observe(ProductRecurrenceObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
        SubscribedProduct::observe(SubscribedProductObserver::class);
        Subscription::observe(SubscriptionObserver::class);
        Setting::observe(SettingObserver::class);
        Voucher::observe(VoucherObserver::class);
    }
}
