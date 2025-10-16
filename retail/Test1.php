<html>

<head>
 <script type="text/javascript" src="jquery-3.5.1.js"></script>
  
</head> 
<body>
<?php

$emp['Edward'] = 12;
$emp['Mercy'] = 13;

$ToJava = json_encode($emp);

?>

<script>
   var localStorag = <?= $ToJava?>;
   console.log (localStorag.Mercy);
</script>

</body>