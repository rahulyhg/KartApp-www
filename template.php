<?php
session_start();
if(isset($_SESSION['userid']) && isset($_SESSION['username']))
{
include("header.php");
echo "<br><br><br><br>Sie sind eingeloggt";

//.....................................................................Eigentlicher Code

<?php
}
else{
include("headernav.php");
?>
<div class="container">
	<br><br><br><br>

	<form method="post" action="cardsets.php">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">Sie sind nicht eingeloggt. Bitte melden Sie sich an!</h3>
		</div>
		<div class="panel-body">
			<label for="exampleInputUser1">Benutzertest</label>
			<input type="text" name="username" class="form-control" id="exampleInputEmail1" placeholder="Benutzername"><br>
			<label for="exampleInputPassword1">Password</label>
			<input type="password" name="password" class="form-control" id="exampleInputPassword1" placeholder="Password"><br>
			<button type="submit" name="button" class="btn btn-default">Login</button>
		</div>
	</div>
</div>
</form>

<?php
}

include('footer.php');

?>
