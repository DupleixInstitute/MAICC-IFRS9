<SCRIPT TYPE="text/javascript">
<!-- 
function checkEmail(email)
{

var mm =document.test.email.value;
var numString = String(mm);
var papi = String("papiso");
newstring= papi.concat(numString);
if(newstring.length == 6) 
   {
      alert("Ensure that the three are all filled in");
	     return false;
   }
 }
//-->
</SCRIPT>


<FORM ACTION="../cgi-bin/mycgi.pl" METHOD=POST name="test">
name:        <INPUT NAME="realname"><BR>
email:       <INPUT NAME="email"><BR>

interest       <INPUT NAME="in" onChange="checkEmail(this.value)"  onFocus="checkEmail(email.value)" ><BR>

destination: <INPUT NAME="desination">
</FORM>
