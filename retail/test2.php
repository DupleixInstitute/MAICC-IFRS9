
function show_text(index,whichdiv){
  <?php
//  $indx = 2;  // Hardcode Works - but index actually varies with ea. call
//  Now try passing JS variable:  does not work
    $indx = echo '<script type="text/JavaScript"> echo index; </script>';  

    $arrCmds   = array_values($arrButtons[$indx]['cat2']);
    $arrRefs   = array_values($arrButtons[$indx]['ref2']);

    $txtSubMenu = '';
    for ($c=0; ($c < count($arrCmds)); $c++) {
      if (($c+1) < count($arrCmds)) {
        $txtSubMenu .= '<a href='.$arrRefs[$c].'>'.$arrCmds[$c].'</a>  |  ';
      }
      else {
        $txtSubMenu .= '<a href='.$arrRefs[$c].'>'.$arrCmds[$c].'</a>';
      }
    }
    echo "thetext = '$txtSubMenu';\n  ";
  ?>

  var text = '<font size="2" face="Arial" color=<?php echo $colDarkGray;?>>'+
                 thetext+'</font>';
    
  if (ie) eval("document.all."+whichdiv).innerHTML=text
  else if (ns6) document.getElementById(whichdiv).innerHTML=text
}
<SCRIPT TYPE="text/javascript">

function checkEmail(email)
{
if(email.length <= 0)
   {
   
else if(email.length <= 0)
alert("enter e mail address");

}}


</SCRIPT>


<FORM ACTION="../cgi-bin/mycgi.pl" METHOD=POST>
name:        <INPUT NAME="realname"><BR>
email:       <INPUT NAME="email" onChange="checkEmail(this.value)"><BR>
destination: <INPUT NAME="desination">
</FORM>
