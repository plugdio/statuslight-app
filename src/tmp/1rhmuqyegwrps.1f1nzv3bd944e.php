<!doctype html>
<html lang="en">
	<head>

<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-162283439-1"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-162283439-1');
</script>

		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">


		<title>Statuslight Online</title>
	</head>
	<body>

	<div id="statusContainer" class="container vh-100 bg-secondary">
		<div class="row p-3">
			<script src="https://richtr.github.io/NoSleep.js/dist/NoSleep.min.js"></script>
	    	<button id="toggle" type="button" class="btn btn-outline-light btn-block active">Touch here to keep the screen on</button>
	    </div>
	    <div class="row p-3 d-flex justify-content-center">
	    	<span style="font-size: 248px;">
	    		<i id="statusIcon" class="fas fa-info text-light"></i>
	    	</span>
	    </div>
		<div class="row p-3 d-flex justify-content-center text-light">
	    	<p id="statusText">Initiating</p>
	    </div>
	</div>

	    <script>
	    	var noSleep = new NoSleep();

	    	var wakeLockEnabled = false;
	    	var toggleButton = document.querySelector("#toggle");
	    	toggleButton.addEventListener('click', function() {
				if (!wakeLockEnabled) {
					console.log('Wake Lock is enabled');
					noSleep.enable(); // keep the screen on!
					wakeLockEnabled = true;
					$('#toggle').text('Screen will stay on');
					$('#toggle').removeClass('active');
	        	} else {
	        		console.log('Wake Lock is disabled');
					noSleep.disable(); // let the screen turn off.
					wakeLockEnabled = false;
					$('#toggle').text('Touch here to keep the screen on');
					$('#toggle').addClass('active');
				}
			}, false);
	    </script>

		<script type="text/javascript">

			var now = Math.floor(Date.now() / 1000);
			var token = null;
			var tokenExpiresOn = 0;

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
//						if (xhr.response.success == true) {
							callback(null, xhr.response);
//						} else {
//							callback(xhr.response.message, xhr.response);
//						}
					} else {
						callback(status, xhr.response);
					}
				};
				xhr.send();
			};

			var getJSON3 = function(method, url, token, callback) {
				var xhr = new XMLHttpRequest();
				xhr.open(method, url, true);
				if (token != null) {
//					xhr.setRequestHeader("authorization", "Bearer " + tokenHeader);
				}
				xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
				xhr.responseType = 'json';
				xhr.onload = function() {
					var status = xhr.status;
					if (status === 200) {
						callback(null, xhr.response);
					} else {
						callback(status, xhr.response);
					}
				};
				if (method == 'GET') {
					xhr.send();	
				} else {
					xhr.send('token=' + token);
				}
				
			};



			function getTokenAndStatus () {
				console.log('Getting the token ...');
				
				now = Math.floor(Date.now() / 1000);

				if (tokenExpiresOn < now + 600) {
					console.log('Token to be refreshed');
					getJSON('<?= ($baseAppPath) ?>/slack/token', null, function(err, data) {
						if (err !== null) {
							console.log('Something went wrong #1: ' + err);
						} else {
							if (data.success == true) {
								console.log('token: ' + data.result.accessToken);
								token = data.result.accessToken;
								tokenExpiresOn = data.result.accessTokenExpiresOn;
							} else {
								console.log('Something went wrong #2: ' + data.message);
							}
						}
						checkStatus( token );
					});
				} else {
					console.log('Using the current token. Expires on:' + new Date(tokenExpiresOn * 1000));
					checkStatus( token );
				}



			}

			function checkStatus (token) {
				console.log('Getting the status ...');
				if (token != null) {
					getJSON3('POST', 'https://slack.com/api/users.getPresence', token, function(err, data) {
						if (err !== null) {
							console.log('Something went wrong #0: ' + err);
							$('#statusContainer').attr('class','container vh-100 bg-secondary');
							$('#statusIcon').attr('class','fas fa-info text-light');
							$('#statusText').text('Something went wrong');
						} else {
							console.log('presence: ' + JSON.stringify(data));
							if (data.presence == 'active') {
								$('#statusContainer').attr('class','container vh-100 bg-success');
								$('#statusIcon').attr('class','fas fa-check text-light');
								$('#statusText').text("Available"); 
							} else if (data.availability == 'Busy') {
								$('#statusContainer').attr('class','container vh-100 bg-danger');
								$('#statusIcon').attr('class','fas fa-phone text-light');
								$('#statusText').text("Busy"); 
							} else if (data.availability == 'DoNotDisturb') {
								$('#statusContainer').attr('class','container vh-100 bg-danger');
								$('#statusIcon').attr('class','fas fa-ban text-light');
								$('#statusText').text("Do Not Disturb"); 
							} else if (data.presence == 'away') {
								$('#statusContainer').attr('class','container vh-100 bg-warning');
								$('#statusIcon').attr('class','fas fa-clock text-light');
								$('#statusText').text("Away"); 
							} else if (data.availability == 'BeRightBack') {
								$('#statusContainer').attr('class','container vh-100 bg-warning');
								$('#statusIcon').attr('class','fas fa-clock text-light');
								$('#statusText').text("Be Right Back"); 
							} else {
								$('#statusContainer').attr('class','container vh-100 bg-secondary');
								$('#statusIcon').attr('class','fas fa-info text-light');
								$('#statusText').text("Something went wrong"); 
							}
						}
					});
				} else {
					console.log('no token');
					$('#statusContainer').attr('class','container vh-100 bg-secondary');
					$('#statusIcon').attr('class','fas fa-info text-light');
					$('#statusText').text('Something went wrong');
				}

			}


			var interval = setInterval(function () {
				getTokenAndStatus(); 
			}, 60000);

			getTokenAndStatus();

		</script>

		<!-- Optional JavaScript -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
	</body>
</html>
