<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

		<title>Statuslight Online</title>
	</head>
	<body>

		<div id="statusContainer" class="container vh-100">

			<br>

			<?php if ($current_page=='REGISTER'): ?>
				<a id="teamsLogin" href="#">Login with Teams</a>
				<br>
				<a id="gcalLogin" href="#">Login with Google</a>
        <br>
        <a id="slackLogin" href="#">Login with Slack</a>

			<?php endif; ?>
		</div>


		<!-- Optional JavaScript -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

  <script type="text/javascript">

      var getJSON = function(url, tokenHeader, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        if (tokenHeader != null) {
          xhr.setRequestHeader("authorization", "Bearer " + tokenHeader);
        }
        xhr.responseType = 'json';
        xhr.onload = function() {
          var status = xhr.status;
          if (status === 200) {
//            if (xhr.response.success == true) {
              callback(null, xhr.response);
//            } else {
//              callback(xhr.response.message, xhr.response);
//            }
          } else {
            callback(status, xhr.response);
          }
        };
        xhr.send();
      };

      var getConfig = function() {
        console.log('Getting the config ... ');

        getJSON('<?= ($base_app_path) ?>/config', null, function(err, data) {
          if (err !== null) {
            console.log('Something went wrong #1: ' + err);
          } else {
            if (data.success == true) {
              $('#teamsLogin').attr("href", data.result.teamsLoginUrl);
              $('#gcalLogin').attr("href", data.result.gcalLoginUrl);
              $('#slackLogin').attr("href", data.result.slackLoginUrl);
            } else {
              console.log('Something went wrong #2: ' + data.message);
            }
          }
        });
      };

      getConfig();
  </script>


	</body>
</html>

