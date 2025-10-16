ALTER TABLE `cust_information`     ADD `loan_category` VARCHAR(30) NOT NULL FIRST,
ALTER TABLE `cust_credit_rating`   ADD `loan_category` VARCHAR(30) NOT NULL FIRST,
ALTER TABLE `creditscore`          ADD `loan_category` VARCHAR(30) NOT NULL FIRST,
ALTER TABLE `approved`             ADD `loan_category` VARCHAR(30) NOT NULL FIRST,
ALTER TABLE `approvals`            ADD `loan_category` VARCHAR(30) NOT NULL FIRST,
ALTER TABLE `returned`             ADD `loan_category` VARCHAR(30) NOT NULL FIRST,


ALTER TABLE `cust_information`     ADD `life_cover_amount` DECIMAL(20,2) NOT NULL AFTER `insurance_replacement_cost`,
ALTER TABLE `cust_information`     ADD `contractual_years_remaining` DECIMAL(20,2) NOT NULL AFTER `employment_contract`,

ALTER TABLE `creditscore`          ADD `contractual_remaining_score` INT(11) NULL AFTER `employment_score`, 
ALTER TABLE `creditscore`          ADD `w_contractual_remaining_score` INT(11) NULL AFTER `contractual_remaining_score`, 


ALTER TABLE `creditscore`          ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST,
ALTER TABLE `cust_information`     ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST,
ALTER TABLE `cust_credit_rating`   ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST,
ALTER TABLE `approved`             ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST,
ALTER TABLE `approvals`            ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST,
ALTER TABLE `returned`             ADD `application_ref` INT(11) NOT NULL COMMENT 'alternative primary key' FIRST;

