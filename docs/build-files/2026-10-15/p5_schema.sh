#!/usr/bin/env bash
# Print the shape of rate_reset_events, eir_rate_periods and schedule_type in one database (read-only).
db="$1"
M=C:/xampp/mysql/bin/mysql
$M -uroot -N -e "
select concat('pending/ran: ', count(*)) from ${db}.migrations where migration in ('2026_10_01_000000_create_eir_rate_periods','2026_10_14_230000_restructure_register_and_modifications');
select concat('columns: ', group_concat(column_name order by column_name separator ',')) from information_schema.columns where table_schema='${db}' and table_name='rate_reset_events';
select concat('status: ', column_type, ' default ', ifnull(column_default,'null'), ' / ', column_comment) from information_schema.columns where table_schema='${db}' and table_name='rate_reset_events' and column_name='status';
select concat('classification_status: ', column_type, ' default ', ifnull(column_default,'null')) from information_schema.columns where table_schema='${db}' and table_name='rate_reset_events' and column_name='classification_status';
select concat('new_schedule_version nullable: ', is_nullable) from information_schema.columns where table_schema='${db}' and table_name='rate_reset_events' and column_name='new_schedule_version';
select concat('unique ', index_name, ': ', group_concat(column_name order by seq_in_index)) from information_schema.statistics where table_schema='${db}' and table_name='rate_reset_events' and non_unique=0 group by index_name;
select concat('eir_rate_periods: ', count(*), ' columns') from information_schema.columns where table_schema='${db}' and table_name='eir_rate_periods';
select concat('schedule_type: ', count(*)) from information_schema.columns where table_schema='${db}' and table_name='contract_cashflow_schedule' and column_name='schedule_type';
"
