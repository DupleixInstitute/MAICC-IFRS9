<?php include 'Upload_Files_Logic.php';?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <link rel="stylesheet" href="Upload_Files_Style.css">
  <title>Download files</title>
</head>
<body>

<table>
<tr>
  <td><img src="img1.gif" alt="nknk" name="g" width="578" height="208"/></td>
  <td><img src="img2.jpg" alt="nknk" name="g" width="578" height="208"/></td>
</tr>
</table>
<table>	
<thead>
    <th><br><br>ID</th>
    <th>Loan<br>Application<br>Number</th>
	<th><br><br>Filename</th>
    <th><br>size <br>(in mb)</th>
    <th><br><br>Downloads</th>
    <th><br><br>Action</th>
</thead>
<tbody>
  <?php foreach ($files as $file): ?>
    <tr>
      <td><?php echo $file['id']; ?></td>
      <td align = center><?php echo $file['application_ref']; ?></td>
      <td><?php echo $file['name']; ?></td>
      <td><?php echo floor($file['size'] / 1000) . ' KB'; ?></td>
      <td align = center><?php echo $file['downloads']; ?></td>
      <td><a href="Upload_Files_Downloads.php?file_id=<?php echo $file['id'] ?>">Download</a></td>
    </tr>
  <?php endforeach;?>

</tbody>
</table>

</body>
</html>