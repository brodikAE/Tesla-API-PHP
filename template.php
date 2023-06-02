<?php

/*
	Tesla API PHP
	template file
	v.0.0.1
*/

class template{

	function main_page($title, $code_verifier, $code_challenge, $state, $request_uri, $timestamp, $tesla_api_redirect, $gen_url) {
return <<<EOF
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
	<title>{$title}</title>
	<style>
		body {
			font-family: "Inter";
			 max-width: 800px;
			 margin: 0 auto;
		}

		label, button {
			font-size: 16px;
			cursor: pointer;
		}

		input {
			border: 3px solid #eee;
			padding: .4em 1em;
			border-radius: 6px;
			width: 800px;
		}

		form {
			display: grid;
			grid-row-gap: 10px;
			justify-content: center;
		}

		button {
			margin-top: 10px;
			background: #133EF5;
			color: #fff;
			padding: 10px;
			border: 0;
			border-radius: 6px;
		}
	</style>
</head>
<body>
	<div class="card border-secondary">
		<form method="post">
			<div class="card-header bg-secondary">
			{$title}
			</div>
			<div class="card-body">
				<input type="hidden" id="go" name="go" value="login">
				<div class="form-row mb-1">
					<label for="code_verifier" class="col-md-4 col-form-label">Code_Verifier:</label>
					<div class="col">
						<input class="form-control" type="text" name="code_verifier" value="{$code_verifier}" readonly>
					</div>
				</div>
				<div class="form-row mb-1">
					<label for="code_challenge" class="col-md-4 col-form-label">Code_Challenge:</label>
					<div class="col">
						<input class="form-control" type="text" name="code_challenge" value="{$code_challenge}" readonly>
					</div>
				</div>
				<div class="form-row mb-1">
					<label for="state" class="col-md-4 col-form-label">State:</label>
					<div class="col">
						<input class="form-control" type="text" name="state" value="{$state}" readonly>
					</div>
				</div>
				<p>
					Unfortunately Tesla has installed a recaptcha, so the automatic login is no longer possible.<br> Please read the individual steps well:
				</p>
				<ol>
					<li>Please <strong><a href="{$request_uri}#{$timestamp}" onclick="teslaLogin();return false;">click here</a></strong>, to log in to Tesla (a popup window will open, please allow popups).</li>
					<li>Step 2: Please enter your Tesla login data on the Tesla website.</li>
					<li>Step 3: If the login was successful, you will receive a <strong>Page not found</strong> information on the Tesla website. Copy the complete web address (e.g. <strong>{$tesla_api_redirect}?code=.....&state=...&issuer=....</strong>)</li>
					<li>Step 4: Paste the copied web address here and press the <strong>Login</strong> Button:</li>
				</ol>
				<div class="form-row mb-1">
					<label for="weburl" class="col-md-4 col-form-label">
						"Page Not Found" URL:
					</label>
					<div class="col">
						<input class="form-control" type="text" name="weburl" id="weburl" required>
					</div>
				</div>
			</div>
			<div class="card-footer">
				<div class="form-row text-center">
					<div class="col">
						<button type="submit" class="btn btn-success" value="Login">Get token</button>
					</div>
				</div>
			</div>
		</form>
	</div>
	<!--
	<hr>
	<h3>
		Refresh Token
	</h3>
	<form method="post">
		<input type="hidden" id="go" name="go" value="refresh">
		Please enter the Bearer Refresh-Token:<br>
		<input name="token" size="100" required><input type="submit" value="Refresh">
	</form>
	-->
	<script>
		function teslaLogin () {
			teslaLogin = window.open("{$gen_url}", "TeslaLogin", "width=800,height=600,status=yes,scrollbars=yes,resizable=yes");
			teslaLogin.focus();
		}
	</script>
</body>
</html>
EOF;
	}

}

?>