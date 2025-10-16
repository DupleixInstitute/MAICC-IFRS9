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
	<div id="ScoresTableContainer" style="margin-left:10px;width: 1500px;"></div>
	<script type="text/javascript">

		$(document).ready(function () {
		    //Prepare jTable
			$('#ScoresTableContainer').jtable({
				title: 'Customer Scores Table - Mortages and Personal Loans',
				paging: true,
				pageSize: 20,
				sorting: true,
				defaultSorting: 'id DESC',
				actions: {
					listAction:   'view_detailed_scores_actions_paged_sorted.php?action=list',
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
					
					omang_passport_num: {
						create: false,
						edit: false,
						title: 'omang_passport_num',
						width: '1%'
					},
					loan_number : {
						create: false,
						edit: false,
						title: 'loan no' ,
						width: '1%'
					},
					loan_category: {
						create: false,
						edit: false,
						title: 'loan category',
						width: '1%'
					},
					marital_status_score: {
						title: 'marital status score',
						create: false,
						edit: false,
						width: '1%'
					},
					w_marital_status_score: {
						create: false,
						edit: false,
						title: 'w_marital status score',
						width: '1%'
					},
					age_score: {
						title: 'age score',
						create: false,
						edit: false,
						width: '1%'
					},
					w_age_score: {
						title: 'w_age score',
						create: false,
						edit: false,
						width: '1%'
					},
					no_of_children_score: {
						title: 'no of children score',
						create: false,
						edit: false,
						width: '2%'
					},
					w_no_of_children_score: {
						title: 'w_no of children score',
						width: '2%'
					},
					dependants_at_home: {
						create: false,
						edit: false,
						title: 'dependants at home score',
						width: '1%'
					},
					w_dependants_at_home: {
						title: 'w_dependants at home score',
						create: false,
						edit: false,
						width: '1%'
					},
					education_level_score: {
						title: 'education level score',
						create: false,
						edit: false,
						width: '1%'
					},
					w_education_level_score: {
						create: false,
						edit: false,
						title: 'w education level score',
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
			$('#ScoresTableContainer').jtable('load');

		});

	</script>
 
  </body>
</html>
