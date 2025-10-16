-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2023 at 04:36 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `credit_scoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `cust_info`
--

CREATE TABLE `cust_info` (
  `application_ref` int(11) NOT NULL,
  `company_reg_no` varchar(220) NOT NULL DEFAULT '',
  `vat_reg_no` varchar(20) NOT NULL,
  `CIF` int(11) DEFAULT NULL,
  `ID` varchar(220) DEFAULT NULL,
  `loan_number` int(11) NOT NULL DEFAULT 0,
  `registration_year` smallint(6) NOT NULL,
  `years_in_business` tinyint(4) NOT NULL,
  `customer_type` varchar(10) NOT NULL,
  `legal_name` varchar(100) NOT NULL,
  `trading_name` varchar(100) NOT NULL,
  `registration_country` varchar(100) NOT NULL,
  `financial_year0` smallint(6) NOT NULL,
  `financial_year1` smallint(6) NOT NULL,
  `financial_year2` smallint(6) NOT NULL,
  `financial_year3` smallint(6) NOT NULL,
  `reporting_month_year0` smallint(6) NOT NULL,
  `reporting_month_year1` smallint(6) NOT NULL,
  `reporting_month_year2` smallint(11) NOT NULL,
  `reporting_month_year3` smallint(6) NOT NULL,
  `months_in_year0` tinyint(4) NOT NULL,
  `months_in_year1` tinyint(4) NOT NULL,
  `months_in_year2` tinyint(4) NOT NULL,
  `months_in_year3` tinyint(4) NOT NULL,
  `audit_status_year0` varchar(25) NOT NULL,
  `audit_status_year1` varchar(25) NOT NULL,
  `audit_status_year2` varchar(25) NOT NULL,
  `audit_status_year3` varchar(25) NOT NULL,
  `real_inflation_year0` float NOT NULL,
  `real_inflation_year1` float NOT NULL,
  `real_inflation_year2` float NOT NULL,
  `real_inflation_year3` float NOT NULL,
  `nominal_inflation_year0` float NOT NULL,
  `nominal_inflation_year1` float NOT NULL,
  `nominal_inflation_year2` float NOT NULL,
  `nominal_inflation_year3` float NOT NULL,
  `industrial_sector` varchar(250) NOT NULL,
  `borrower_present_address` varchar(250) NOT NULL,
  `street_name_and_number` varchar(250) DEFAULT NULL,
  `years_at_present_address` varchar(10) NOT NULL,
  `e_mail` varchar(220) NOT NULL,
  `loan_amount_requested` double DEFAULT NULL,
  `property_type` varchar(250) DEFAULT NULL,
  `open_market_value` int(11) DEFAULT NULL,
  `loan_type` varchar(250) DEFAULT NULL,
  `loan_maturity_requested` int(11) DEFAULT NULL,
  `current_interest_rate` double DEFAULT NULL,
  `insurance_replacement_cost` int(11) DEFAULT NULL,
  `estimated_insurance_premium` double DEFAULT NULL,
  `estimated_installment` double DEFAULT NULL,
  `estimated_installment_insurance` double DEFAULT NULL,
  `loan_to_value_policy` double DEFAULT NULL,
  `loan_to_value` double DEFAULT NULL,
  `bond_plot` varchar(200) DEFAULT NULL,
  `town` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `permanent_country_of_residence` varchar(50) DEFAULT NULL,
  `location_of_acquired_real_estate` varchar(50) DEFAULT NULL,
  `main_bank` varchar(50) DEFAULT NULL,
  `second_bank` varchar(50) DEFAULT NULL,
  `third_bank` varchar(50) DEFAULT NULL,
  `age_of_relationship` varchar(50) DEFAULT NULL,
  `savings_Account` varchar(50) DEFAULT NULL,
  `deposit_Account` varchar(50) DEFAULT NULL,
  `share_Account` varchar(50) DEFAULT NULL,
  `ST_Loans` varchar(50) DEFAULT NULL,
  `mortgages` varchar(50) DEFAULT NULL,
  `total_bbs_products` int(11) DEFAULT NULL,
  `bbs_arreas_12months` int(11) DEFAULT NULL,
  `renegotiated` int(11) DEFAULT NULL,
  `why_renegotiated` varchar(25) DEFAULT NULL,
  `loan_instalment6` double NOT NULL,
  `loan_instalment7` double NOT NULL,
  `loan_instalment8` double NOT NULL,
  `loan_instalment9` double NOT NULL,
  `loan_name1` varchar(150) NOT NULL,
  `loan_name2` varchar(150) NOT NULL,
  `loan_name3` varchar(150) NOT NULL,
  `loan_name4` varchar(150) NOT NULL,
  `loan_name5` varchar(150) NOT NULL,
  `loan_name6` varchar(150) NOT NULL,
  `loan_name7` varchar(150) NOT NULL,
  `loan_name8` varchar(150) NOT NULL,
  `loan_name9` varchar(150) NOT NULL,
  `loan_instalment1` double NOT NULL,
  `loan_instalment2` double NOT NULL,
  `loan_instalment3` double NOT NULL,
  `loan_instalment4` double NOT NULL,
  `loan_instalment5` double NOT NULL,
  `total_instalments` double NOT NULL,
  `number_of_loans_outstanding` int(11) DEFAULT NULL,
  `ITC_REF` varchar(50) DEFAULT NULL,
  `ITC_subject_no` varchar(50) DEFAULT NULL,
  `paid_debt` int(11) DEFAULT NULL,
  `judgment` int(11) DEFAULT NULL,
  `default_data` int(11) DEFAULT NULL,
  `trace_alerts` int(11) DEFAULT NULL,
  `blacklist_flag` varchar(10) DEFAULT NULL,
  `fraud_alerts` varchar(10) DEFAULT NULL,
  `status` varchar(220) DEFAULT NULL,
  `reason` varchar(250) DEFAULT NULL,
  `creation` datetime DEFAULT NULL,
  `deduct` varchar(220) DEFAULT NULL,
  `username` varchar(220) DEFAULT NULL,
  `debtserviceratio` int(11) DEFAULT NULL,
  `tax` int(11) DEFAULT NULL,
  `tax2` int(11) DEFAULT NULL,
  `monthly_payment` double NOT NULL,
  `loan_category` varchar(30) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `borrower_name` varchar(50) NOT NULL,
  `other_names` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `gender` varchar(1) NOT NULL,
  `spouse_partner_co_borrower_name` varchar(50) NOT NULL,
  `spouse_surname` varchar(50) NOT NULL,
  `rate_type_requested` varchar(25) DEFAULT NULL,
  `total_revenues_affordability` decimal(20,2) NOT NULL,
  `affordability_policy` float NOT NULL,
  `affordability_ratio` float NOT NULL,
  `borrower_birth` date DEFAULT NULL,
  `spouse_birth` date DEFAULT NULL,
  `wedding` date DEFAULT NULL,
  `divorce` date DEFAULT NULL,
  `marital_status` varchar(30) NOT NULL,
  `marital_contract_type` varchar(30) NOT NULL,
  `spouse_borrowing_relationship` varchar(30) NOT NULL,
  `total_number_of_children` tinyint(4) NOT NULL,
  `children0_to12` tinyint(4) NOT NULL,
  `childred13_to18` tinyint(4) NOT NULL,
  `children19_to26` tinyint(4) NOT NULL,
  `other_dependents_grandparents` tinyint(4) NOT NULL,
  `other_dependents_aunts` tinyint(4) NOT NULL,
  `other_dependents` tinyint(4) NOT NULL,
  `total_dependants` tinyint(4) NOT NULL,
  `borrower_education` varchar(50) NOT NULL,
  `professional_category` varchar(50) NOT NULL,
  `employment_contract` varchar(50) NOT NULL,
  `contractual_years_remaining` varchar(30) NOT NULL,
  `number_of_years_at_employer` varchar(30) NOT NULL,
  `household_proffessional_revenue` varchar(15) NOT NULL,
  `annual_salary` decimal(20,2) NOT NULL,
  `fixed_permanent_allowances` decimal(20,2) NOT NULL,
  `total_revenue_for_affordability` decimal(20,2) NOT NULL,
  `partner_annual_salary` decimal(20,2) NOT NULL,
  `partner_permanent_allowances` decimal(20,2) NOT NULL,
  `total_allowable_household_revenues` decimal(20,2) NOT NULL,
  `partner_revenue_for_affordability` decimal(20,2) NOT NULL,
  `no_of_credit_card` decimal(1,0) NOT NULL,
  `card_held_since` varchar(20) NOT NULL,
  `age` smallint(6) NOT NULL,
  `loan_instalment10` decimal(20,2) DEFAULT NULL,
  `loan_instalment11` decimal(20,2) DEFAULT NULL,
  `loan_instalment12` decimal(20,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cust_info`
--
ALTER TABLE `cust_info`
  ADD PRIMARY KEY (`application_ref`),
  ADD KEY `company_reg_no` (`company_reg_no`),
  ADD KEY `loan_number` (`loan_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cust_info`
--
ALTER TABLE `cust_info`
  MODIFY `application_ref` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
