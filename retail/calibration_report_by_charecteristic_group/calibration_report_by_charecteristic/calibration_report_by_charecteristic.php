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
				title: 'Model Calibration Table Results- By Charecteristic',
				paging: true,
				pageSize: 20,
				sorting: true,
				defaultSorting: 'charecteristic_group ASC',
				actions: {
					listAction:   'calibration_report_by_charecteristic_actions_paged_sorted.php?action=list',
				},
				fields: {
					/**
					id: {
						key: true,
						title: 'id',
						create: false,
						edit: false,
						width: '1%',
						list: false
					},
					**/
					charecteristic: {
						key:true,
						create: false,
						edit: false,
						title: 'Characteristic',
						width: '1%'
					},
					charecteristic_group: {
						title: 'Characteristic Group',
						create: false,
						edit: false,
						width: '1%'
					},
					score_option_upper: {
						title: 'Score Option',
						create: false,
						edit: false,
						width: '1%'
					},
					min_score: {
						create: false,
						edit: false,
						title: 'min_score',
						width: '1%'
					},
					max_score: {
						title: 'max_score',
						create: false,
						edit: false,
						width: '1%'
					},
					total_min_scores: {
						create: false,
						edit: false,
						title: 'total_min_scores',
						width: '1%'
					},
					total_max_scores: {
						title: 'total_max_scores',
						create: false,
						edit: false,
						width: '1%'
					},
					effective_weight: {
						title: 'effective_weight',
						create: false,
						edit: false,
						width: '1%'
					}
				}
			});

			//Load person list from server
			$('#CalibrationTableContainer').jtable('load');

		});

	</script>
 
  </body>
</html>
