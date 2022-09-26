# Release

## v1.23.2

```bash
php artisan migrate
php artisan merchant:fix --update-subheader-notification-single-order
```
## v1.23.0

```bash
php artisan migrate
php artisan db:seed TableColumnSeeder
php artisan db:seed \\SetFAQsDefaultToDisable_2022_09_05_142300
php artisan db:seed \\ConvertS3ImageToWebp_2022_09_06_214300
php artisan db:seed \\SetDeliverySettingsDefaultToFalse_2022_09_07_110000
php artisan db:seed \\SetMembersPageButtonToMerchant_2022_09_09_004700
```
## v1.22.0

```bash
php artisan migrate
```

## v1.21.0

```bash
php artisan migrate
php artisan db:seed BlastProductSeeder
php artisan db:seed PostSeeder
```

## v1.20.3

```bash
php artisan merchant:fix --update-default-custom-fields
```

## v1.19.0

```bash
php artisan migrate
php artisan db:seed ShippingMethodCountrySeeder
php artisan db:seed \\SetInternationalShippingMethod_2022_08_10_082500
php artisan db:seed \\SetDefaultShippingMethod_2022_08_12_061000
```

## v1.18.3

```bash
php artisan migrate
php artisan merchant:fix --update-order-notifications --update-order-notification-column="subheader" --update-order-notification-value="Please pay {totalPrice} for your {subscriptionTermSingular} with {merchantName} on or before {billingDate}"
```

## v1.18.2

```bash
php artisan migrate
```

## v1.18.1

```bash
php artisan db:seed TableColumnSeeder
php artisan db:seed SocialLinkIconSeeder
```

## v1.18.0

```bash
php artisan migrate
php artisan db:seed \\SetCustomComponentsDefault_2022_07_28_084800
php artisan db:seed \\SetSoldCountToAllOrders_2022_07_27
php artisan db:seed \\CascadeShippableStatusToProductLevel_2022_08_11_102500
```

## v1.17.2

```bash
php artisan migrate
php artisan db:seed ProductRecurrenceSeeder
```
## v1.17.0

```bash
php artisan migrate
php artisan db:seed SettingSeeder
php artisan db:seed MerchantButtonsSeeder
php artisan db:seed NewProductVariantSeeder
php artisan db:seed \\SetDefaultCopies_2022_07_15_105500
php artisan db:seed \\SetProductRecurrencePricing_2022_07_21_223500
php artisan db:seed \\SetProductGroupSlug_2022_07_28_124800
```

## v1.16.1

```bash
php artisan db:seed --class=SetAutoChargeStartAndEndSettings
```

### Notes

-   Upload and Overwrite the existing image in prod/staging/sandbox S3
    https://drive.google.com/file/d/1faZLZD1y6twoO7w2z-7qDF9PF5k9SO83/edit
    S3 path: "images/social_links/"

-   Upload to staging/sandbox/prod S3
    https://drive.google.com/file/d/1se_puK5Wjv6OqsBy5kr5Q5I2K4r46TtM/view?usp=sharing
    S3 path "images/assets/"

## v1.16.0

```bash
php artisan migrate
php artisan db:seed --class=SocialLinkIconSeeder
php artisan db:seed --class=\\SetMerchantMembersLoginModalText_2022_07_07_193000
```

### Notes

-   Upload prod/staging/sandbox S3
    https://drive.google.com/drive/u/1/folders/1A55LoZr4nqlYzoLqpb5HxzklK7mxqwlq
    All images under this path S3 path: "images/social_links/"

## v1.15.0

```bash
php artisan migrate
php artisan db:seed OrderNotificationSeeder
php artisan db:seed \\AddPlaceholdersToPaymentErrorResponses_2022_07_04_153200
php artisan db:seed \\SetOrderIsAutoChargeFlagFalseForDigitalAndBank_2022_07_07_134800
```
## v1.14.2
### Notes

-   Upload prod S3
    https://drive.google.com/file/d/1eWVlwgGNR8YBIPjojCn5s_AZiaQYKGfJ/view?usp=sharing
    S3 path: "images/assets/discord_icon.png" 

## v1.14.0

```bash
php artisan migrate
php artisan db:seed --class=\\SetDeepLinkInExistingProducts_2022_06_24_075000
```

## v1.13.0

```bash
php artisan migrate
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=\\CreateHomeCardsPermission_2022_06_01_094500
php artisan db:seed --class=SetHomePageSetting
php artisan db:seed --class=\\SetDefaultMerchantUserCountry_2022_05_31_152400
php artisan db:seed --class=\\SetDefaultMerchantCountry_2022_05_31_160000
php artisan db:seed --class=\\SetCustomerFormattedNumber_2022_05_31_162800
php artisan db:seed --class=\\MigrateDefaultRemindersToFollowUpEmails_2022_06_08_131600
php artisan db:seed --class=\\SetDefaultTableCustomerValue_2022_06_22_290100
```

### Notes

-   Upload prod S3
    https://drive.google.com/file/d/1EF8s5BhOtzuGGELboifbVJ5gISzVjih5/view?usp=sharing
    S3 path: "images/discord_white.png" 

## v1.12.0

```bash
composer install
php artisan migrate
php artisan db:seed --class=OrderStatusSeeder
php artisan db:seed --class=TableColumnSeeder
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=\\SetAutoChargeFlagToExistingSubscriptions_2022_04_27_171000
php artisan db:seed --class=\\SetConvenienceFeeOnBanks_2022_04_28_160700
php artisan db:seed --class=\\SetSemiAnnualAndBimonthlyRecurrences_2022_04_29_074900
php artisan db:seed --class=\\SetProductRecurrences_2022_04_29_090000
php artisan db:seed --class=\\MigrateMerchantConvenienceFee_2022_05_05_070200
php artisan db:seed --class=\\SetProductSlug_2022_05_14_002000
php artisan db:seed --class=\\RearrangeSoringOfMerchantRecurrences_2022_05_23_120800
```
### Notes

-   Upload prod S3
    https://drive.google.com/file/d/1l10bvGTQEeyVdL9u8TmlmXLIWak6iCNw/view?usp=sharing
    S3 path: "images/viber_notification.png" 

    https://drive.google.com/file/d/1uIePps5v4zO3YJoa-EoA3mrc4IdgTaSt/view?usp=sharing
    S3 path: "images/play-button.png" 

-   Encode this to google analytics 
    https://docs.google.com/spreadsheets/d/1x2C6LjHE-70ogS4etXRJnW1EOSfjYxAELOLSCZKRVx0/edit#gid=0

## v1.11.5

```bash
php artisan migrate
php artisan webhook:viber-setup --type=merchant
php artisan db:seed --class=SocialLinkIconSeeder
php artisan db:seed --class=SetConsoleDocumentationSettings
php artisan db:seed --class=\\SetInfoMerchantBlasts_2022_03_30_090300
php artisan db:seed --class=\\CreateFollowUpPermission_2022_03_31_104500
php artisan db:seed --class=\\SetDefaultFontSettings_2022_04_01_155000
```
### Notes

-   Create Viber account for Sandbox and PROD
-   Setup SendGrid keys for Sandbox and Prod?
-   Upload social link icons to prod S3
    https://drive.google.com/drive/folders/1Ovc1mGKPUTwZK4gbfedfFb790dOE6Jk8?usp=sharing
    S3 path: "/images/social_links/viber.png" (refer to SocialLinkIconSeeder)

## v1.11.4

```bash
php artisan migrate
php artisan db:seed --class=\\CreateSavedSearchMerchants_2022_04_04_111600
php artisan db:seed --class=\\CreateShopifyCustomer_2022_04_04_124000

```
## v1.10.3

```bash
php artisan migrate
php artisan metafield-cleanup:fix
```
## v1.10.0

```bash
php artisan migrate
php artisan db:seed --class=UserPermissionSeeder
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=\\SetMerchantTotalPaidTransactions_2022_03_21_115500
php artisan db:seed --class=\\SetMerchantCTAButton_2022_03_22_073300
```

## v1.9.4

```bash
php artisan migrate
php artisan db:seed --class=\\UpdateShopifyCustomImages_2022_03_03_191100
```

## v1.9.3

```bash
php artisan migrate
```

## v1.9.0

```bash
php artisan migrate
php artisan subscription:fix --shipping-fee=true --shipping-method-id=215 --product-id=3325
```

## v1.8.0

```bash
php artisan migrate
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=KycLinkSeeder
php artisan db:seed --class=\\CreateCPPermissions_2022_01_30_012000
php artisan merchant:register-shopify-webhook --topics='collections/delete','collections/update'
```

## Hotfix Batch 1

```bash
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=SetIdleSeeder
```

## PCI-DSS

```bash
php artisan migrate
php artisan db:seed --class=LoginLockoutSeeder
php artisan db:seed --class=\\SetInitialPasswordHistory_2022_01_31_154700
php artisan db:seed --class=\\CreateCPPermissions_2022_01_30_012000
php artisan db:seed --class=\\CreateConsolePermission_2022_01_30_042100
php artisan db:seed --class=\\UpdateUsersPermission_2022_01_31_021400
php artisan db:seed --class=\\UpdateMerchantUsersPermission_2022_01_31_025600
```

### Notes

-   Add to env both API and Worker `IDLE_LIMIT=15`

## v1.7.0

```bash
php artisan migrate

php artisan db:seed --class=\\SetMerchantPaymentTypes_2021_11_23_082700
php artisan db:seed --class=\\MigrateCustomerPaymayaWallets_2021_11_28_224400
php artisan db:seed --class=\\SetExistingUsersAsVerified_2021_12_01_101500
```

### Notes

-   Update PesoPay datafeed URL
-   Update Cloudflare config for PesoPay datafeed

## v1.6.0

```bash
php artisan migrate
php artisan db:seed --class=CountryCodeSeeder
php artisan db:seed --class=DiscountTypeSeeder
php artisan db:seed --class=PaymayaMidSeeder

// Import PayMaya MIDs from Excel

php artisan db:seed --class=\\MigrateCustomerCards_2021_10_25_134700
php artisan db:seed --class=\\MigratePaymayaMid_2021_10_27_144400
php artisan db:seed --class=\\UpdateCompletedSubscriptions_2021_10_26_204800
php artisan db:seed --class=\\SetMerchantAnnualRecurrence_2021_10_22_053800
php artisan db:seed --class=\\SetDefaultProductVariant_2021_11_01_125900
php artisan db:seed --class=\\SetCustomersCountry_2021_10_24_070000
php artisan db:seed --class=\\ConvertProductRecurrencePriceToDiscountValue_2021_10_26_145900
```

### Notes

## v1.5.0

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PaymayaWalletSeeder
php artisan db:seed --class=\\SetUserRoles_2021_09_27_115000
php artisan db:seed --class=\\MigrateRecurrencesToProducts_2021_10_01_113000
php artisan db:seed --class=\\MigrateGroupNumbers_2021_09_24_143300
php artisan db:seed --class=\\SortPaymentSchedule_2021_10_04_160600
php artisan db:seed --class=\\SetMerchantRecurrenceSingleToSingleOrder_2021_10_05_183500
```

### Notes

-   Set `IsPaymayaWalletEnabled` value type to `boolean` value to `1`

## v1.4.0

```bash
php artisan migrate
php artisan passport:client --password --name="Customers Password Grant Client" --provider=customers
php artisan passport:client --personal --name="Customers Personal Access Client" --provider=customers
php artisan merchant:fix
php artisan subscription:fix
php artisan webhook:viber-setup
php artisan db:seed --class=\\SetMerchantQuarterlyRecurrence_2021_09_03_110300

```

### Notes

-   Add viber prod credentials to ENV

## v1.3.x

```bash
php artisan migrate
php artisan db:seed --class=\\SetMerchantSingleRecurrence_2021_08_19_141100
php artisan db:seed --class=\\SetDefaultSingleRecurrenceText_2021_08_25_101600
php artisan oauth:clear
```

### Notes

-   Set `MerchantMaxAmountLimit` value type to `float` value to `250000`

## v1.2.x

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=\\MigrateMerchantUsers_2021_07_28_104300
php artisan db:seed --class=\\CreateDefaultShippingMethodGroups_2021_07_29_105500
php artisan db:seed --class=\\SetMerchantRecurrences_2021_07_27_210500
php artisan oauth:clear
```

## v1.0.0

```bash
php artisan migrate --seed
php artisan bk-passport:install
```

### Notes

-   Upload asset images, templates, etc. to production cdn (For example brankas new security logos, brankas bank logos)
-   Copy `https://sandbox-cdn.bukopay.ph/templates/SubscriptionPriceEditTemplate.xlsx` to production cdn
-   Set `SubscriptionPriceEditTemplateLink` value type to `string` value to `https://cdn.bukopay.ph/templates/SubscriptionPriceEditTemplate.xlsx`
-   Set `MerchantFinancesTemplate` value type to `string` value to `https://cdn.bukopay.ph/templates/MerchantFinancesTemplate.xlsx`
-   Set `CustomMerchants` value type to `array` value to `{"mosaic": 0, "yardstick": 0}` note `wait for the creation of Mosaic and Yardstick`
