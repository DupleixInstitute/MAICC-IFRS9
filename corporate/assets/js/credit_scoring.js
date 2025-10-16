function manager_reports_menu_validation(menuitem) {
  switch (menuitem) {
     case "check_status":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'checkstatus.php');
     break;
     case "customer_portfolio":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'scores1.php');
     break;
     case "returned_per_consultant":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'returned.php');
     break;
     case "status_reasons":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'status_report.php');
     break;
     case "approvals_by_location":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'locations.php');
     break;
     case "rate_type_requested_counts":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'product_report.php');
     break;
     case "approvals_by_employment_contract":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'employment.php');
     break;
     case "approvals_by_rate_type":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'ratet_report.php');
     break;
     case "table_dump": 
		$("#manager_report").attr('action', 'table_dump/table_dump.php');
     break;
     case "approvals_by_consultant":     
        //$('#freeform_first_name').removeAttr('required');
        $('#myid').attr('required', 'required');
        $('#loan_number').attr('required', 'required');
		$("#manager_report").attr('action', 'consultant_report.php');
     break;
	 
	 
  }

}