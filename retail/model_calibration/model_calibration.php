<html>
  <head>

    <link href="../assets/jtable/themes/redmond/jquery-ui-1.8.16.custom.css" rel="stylesheet" type="text/css" />
	<link href="../assets/jtable/scripts/jtable/themes/lightcolor/blue/jtable.css" rel="stylesheet" type="text/css" />
	
	<script src="../assets/jtable/scripts/jquery-1.6.4.min.js" type="text/javascript"></script>
    <script src="../assets/jtable/scripts/jquery-ui-1.8.16.custom.min.js" type="text/javascript"></script>
    <script src="../assets/jtable/scripts/jtable/jquery.jtable.js" type="text/javascript"></script>

	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		table {
		  border-collapse: collapse;
		  border-spacing : 0;
		  width          : 100%;
		  border         : 1px solid #ddd;
		}

		th, td {
		  text-align: left;
		  font-size:10px;
		  padding: 8px;
		}

		tr:nth-child(even){background-color: #f2f2f2}
	</style>
	
  </head>
  <body>
	<div id="CalibrationTableContainer" style="margin-left:10px;width: 1200px;"></div>
	<script type="text/javascript">

		$(document).ready(function () {

		    //Prepare jTable
			$('#CalibrationTableContainer').jtable({
				title: 'Model Calibration Table - Mortages and Personal Loans',
				paging: true,
				pageSize: 20,
				sorting: true,
				defaultSorting: 'charecteristic_group ASC',
				actions: {
					listAction:   'model_calibration_actions_paged_sorted.php?action=list',
					createAction: 'model_calibration_actions_paged_sorted.php?action=create',
					updateAction: 'model_calibration_actions_paged_sorted.php?action=update',
					deleteAction: 'model_calibration_actions_paged_sorted.php?action=delete'
				},
				fields: {
					id: {
						key: true,
						title: 'id',
						create: false,
						edit: false,
						width: '1%',
						list: true
					},
					
					charecteristic: {
						title: 'Characteristic',
						width: '1%'
					},
					charecteristic_group: {
						title: 'Characteristic Group',
						width: '1%'
					},
					score_option_upper: {
						title: 'Score Option',
						width: '2%'
					},
					reject_threshold: {
						title: 'Reject Threshold',
						width: '1%'
					},
					total_scores: {
						title: 'Total Scores',
						width: '1%'
					},
					mortgage_max_score: {
						title: 'Max Score-Mortgages',
						width: '1%'
					},
					mortgage_score_adj: {
						title: 'Score Adj-Mortgages',
						width: '1%'
					},
					mortgage_effective_score: {
						title: 'Final Score-Mortgages',
						create: false,
						edit: false,
						width: '1%'
					},
					unsecured_loan_max_score: {
						title: 'Max Score-Pers.Loans',
						width: '1%'
					},
					unsecured_loan_score_adj: {
						title: 'Score Adj-Pers.Loans',
						width: '1%'
					},
					unsecured_loan_effective_score: {
						title: 'Final Score-Pers.Loans',
						create: false,
						edit: false,
						width: '1%'
					},
					mortgage_overall_effective_weight: {
						title: 'Final Score-Pers.Loans',
						create: false,
						edit: false,
						width: '1%'
					},
					unsecured_loan_overall_effective_weight: {
						create: false,
						edit: false,
						title: 'Final Score-Pers.Loans',
						width: '1%'
					},
					created_at: {
						title: 'Record Date',
						width: '1%',
						type: 'date',
						create: false,
						edit: false
					}
				}
			});

			//Load person list from server
			$('#CalibrationTableContainer').jtable('load');

		});

	</script>
 
  </body>
</html>
