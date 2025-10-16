<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrenciesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('currencies')->truncate();
        $statement = "INSERT INTO `currencies` (`code`, `name`, `symbol`, `xrate`) VALUES
('AED', 'United Arab Emirates Dirham', 'ARE', '3.67310'),
('AUD', 'Australian Dollar', '$', '1.30887'),
('BRL', 'Brazilian Real', 'R$', '3.28990'),
('CAD', 'Canadian Dollar', '$', '1.28699'),
('CHF', 'Swiss Franc', 'Fr', '0.99879'),
('CLP', 'Chilean Peso', '$', '634.92703'),
('CNY', 'Chinese Yuan', '¥', '6.65090'),
('CZK', 'Czech Koruna', 'Kč', '22.07896'),
('DKK', 'Danish Krone', 'kr', '6.39641'),
('EUR', 'Euro', '€', '0.85947'),
('GBP', 'British Pound', '£', '0.76160'),
('HKD', 'Hong Kong Dollar', '$', '7.80429'),
('HUF', 'Hungarian Forint', 'Ft', '266.94000'),
('IDR', 'Indonesian Rupiah', 'Rp', '13579.08005'),
('ILS', 'Israeli New Shekel', '₪', '3.52770'),
('INR', 'Indian Rupee', 'INR', '65.02500'),
('JPY', 'Japanese Yen', '¥', '114.15367'),
('KES', 'Kenya shillings', 'kes', '103.83500'),
('KRW', 'Korean Won', '₩', '1130.15833'),
('MXN', 'Mexican Peso', '$', '19.22180'),
('MYR', 'Malaysian Ringgit', 'RM', '4.23999'),
('NOK', 'Norwegian Krone', 'kr', '8.18854'),
('NZD', 'New Zealand Dollar', '$', '1.46185'),
('PHP', 'Philippine Peso', '₱', '51.82000'),
('PKR', 'Pakistan Rupee', '₨', '105.34574'),
('PLN', 'Polish Zloty', 'zł', '3.65669'),
('RUB', 'Russian Ruble', '₽', '57.79350'),
('SEK', 'Swedish Krona', 'kr', '8.37433'),
('SGD', 'Singapore Dollar', '$', '1.36899'),
('THB', 'Thai Baht', '฿', '33.28950'),
('TRY', 'Turkish Lira', '₺', '3.82340'),
('TWD', 'Taiwan Dollar', '$', '30.27400'),
('USD', 'US Dollar', '$', '1.00000'),
('VEF', 'Bolívar Fuerte', 'Bs.', '10.06907'),
('ZAR', 'South African Rand', 'R', '14.24180'),
('ZWD', 'Zim Dollar', '$', NULL);
";
        DB::unprepared($statement);
    }
}
