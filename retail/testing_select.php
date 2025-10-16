<html>
<head>
<script type="text/javascript">
function makeDisable(){
    var x=document.getElementById("mySelect")
    x.disabled=true
}
function makeEnable(){
    var x=document.getElementById("mySelect")
    x.disabled=false
}
</script>
</head>

<body>
<form>
<select id="mySelect">
  <option>Apple</option>
  <option>Banana</option>
  <option>Orange</option>
</select>
<input type="button" onclick="makeDisable()" value="Disable list">
<input type="button" onclick="makeEnable()" value="Enable list">
</form>
</body>

</html>

