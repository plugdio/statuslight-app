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

			var getJSON2 = function(method, url, tokenHeader, payload, callback) {
				var xhr = new XMLHttpRequest();
				xhr.open(method, url, true);
				if (tokenHeader != null) {
					xhr.setRequestHeader("authorization", "Bearer " + tokenHeader);
				}
				xhr.setRequestHeader("Content-Type", "application/json");
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
					xhr.send(JSON.stringify(payload));
				}
				
			};

			function getTokenAndStatus () {
				console.log('Getting the token ...');
				
				now = Math.floor(Date.now() / 1000);

				if (tokenExpiresOn < now + 600) {
					console.log('Token to be refreshed');
					getJSON('<?= ($baseAppPath) ?>/gcal/token', null, function(err, data) {
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
						checkCalendarAndStatus( token );
					});
				} else {
					console.log('Using the current token. Expires on:' + new Date(tokenExpiresOn * 1000));
					checkCalendarAndStatus( token );
				}



			}

			function checkCalendarAndStatus (token) {
				console.log('Getting calendars ...');

				primaryCalendar = null;

				if (token != null) {
					getJSON('https://www.googleapis.com/calendar/v3/users/me/calendarList?minAccessRole=freeBusyReader', token, function(err, data) {
						if (err !== null) {
							console.log('Something went wrong #0: ' + err);
							$('#statusContainer').attr('class','container vh-100 bg-secondary');
							$('#statusIcon').attr('class','fas fa-info text-light');
							$('#statusText').text('Something went wrong');
						} else {
//							console.log('calendars: ' + JSON.stringify(data.items));

							if (primaryCalendar == null) {
//								console.log('getting primaryCalendar');
								for (var i = data.items.length - 1; i >= 0; i--) {
//									console.log('calendar: ' + data.items[i].id);
									if (data.items[i].primary) {
										primaryCalendar = data.items[i].id;
										break;
									}
								}
							}
							console.log('primaryCalendar: ' + primaryCalendar);
							checkStatus( token, primaryCalendar );
						}
					});
				} else {
					console.log('no token');
					$('#statusContainer').attr('class','container vh-100 bg-secondary');
					$('#statusIcon').attr('class','fas fa-info text-light');
					$('#statusText').text('Something went wrong');
				}

			}

			function checkStatus (token, primaryCalendar) {

				var startTime = new Date();
				var endTime = new Date(startTime.getTime() + 1 * 60000);
				var startTimeIso = startTime.toISOString();
				var endTimeIso = endTime.toISOString();

				var request = new Object();
				request.timeMin = startTimeIso;
				request.timeMax = endTimeIso;
				request.items = [];
				var item = {id:primaryCalendar};
				request.items.push(item);
								
				console.log(JSON.stringify(request));

				console.log('Getting the status for ' + startTimeIso);

				if (token != null) {
					getJSON2('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', token, request, function(err, data) {
						if (err !== null) {
							console.log('Something went wrong #0: ' + err);
							$('#statusContainer').attr('class','container vh-100 bg-secondary');
							$('#statusIcon').attr('class','fas fa-info text-light');
							$('#statusText').text('Something went wrong');
						} else {
							console.log('freebusy: ' + JSON.stringify(data));
							busyLength = data.calendars[primaryCalendar].busy.length;
							console.log('busy lenght: ' + busyLength);
							if (busyLength == 0) {
								$('#statusContainer').attr('class','container vh-100 bg-success');
								$('#statusIcon').attr('class','fas fa-check text-light');
								$('#statusText').text("Available"); 
							} else if (busyLength > 0) {
								$('#statusContainer').attr('class','container vh-100 bg-danger');
								$('#statusIcon').attr('class','fas fa-ban text-light');
								$('#statusText').text("Busy"); 
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

