<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<body>
<?php
echo "thato";
$rev4afford=$_POST['rev4afford'];
echo $rev4afford;
echo "thato";
$loanandinsurance=$_POST['loanandinsurance'];

$loan1=NULL;
$loan2=NULL;
$loan3=NULL;
$loan4=NULL;

$installment1=$_POST['installment1'];
$ainstallment1=$_POST['ainstallment1'];
$ainstallment2=$_POST['ainstallment2'];
$binstallment1=$_POST['binstallment1'];
$binstallment2=$_POST['binstallment2'];
$binstallment3=$_POST['binstallment3'];
$cinstallment1=$_POST['cinstallment1'];
$cinstallment2=$_POST['cinstallment2'];
$cinstallment3=$_POST['cinstallment3'];
$cinstallment4=$_POST['cinstallment4'];
$numloans=$_POST['loans_outstanding'];
echo $numloans; echo "Papip";

if ($numloans==0)
{
$debtserviceratio=($loanandinsurance / $rev4afford )*100;
}

else if ($numloans==1)
{
//echo "chose 1";
$debtserviceratio=(($installment1 +  $loanandinsurance )/ $rev4afford )*100;
}


else if ($numloans==2)
{
//echo "chose 2";
$debtserviceratio=(($ainstallment1 + $ainstallment2 + $loanandinsurance )/ $rev4afford )*100;
}

if ($numloans==3)
{
//echo "chose 3";
$debtserviceratio=(($installment1 + $binstallment2 + $binstallment3 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==4)
{
//echo "chose 4";
$debtserviceratio=(($cinstallment1 + $cinstallment2 + $cinstallment3 + $cinstallment4 + $loanandinsurance )/ $rev4afford )*100;
}
//echo "hhh";
echo $debtserviceratio;
?>
</body>
</html>
