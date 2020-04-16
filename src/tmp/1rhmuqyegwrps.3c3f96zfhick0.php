<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

		<title>Hello, world!</title>
	</head>
	<body id="statusBody">

		<br>

		<script src="https://richtr.github.io/NoSleep.js/dist/NoSleep.min.js"></script>

    	<input type="button" id="toggle" value="Wake Lock is disabled" />

	    <script>
	    	var noSleep = new NoSleep();

	    	var wakeLockEnabled = false;
	    	var toggleEl = document.querySelector("#toggle");
	    	toggleEl.addEventListener('click', function() {
				if (!wakeLockEnabled) {
					noSleep.enable(); // keep the screen on!
					wakeLockEnabled = true;
					toggleEl.value = "Wake Lock is enabled";
//					document.body.style.backgroundColor = "green";
	        	} else {
					noSleep.disable(); // let the screen turn off.
					wakeLockEnabled = false;
					toggleEl.value = "Wake Lock is disabled";
//					document.body.style.backgroundColor = "";
				}
			}, false);
	    </script>

		<script type="text/javascript">

			var getJSON = function(url, callback) {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', url, true);
				xhr.responseType = 'json';
				xhr.onload = function() {
					var status = xhr.status;
					if (status === 200) {
						if (xhr.response.success == true) {
							callback(null, xhr.response);
						} else {
							callback(xhr.response.message, xhr.response);
						}
					} else {
						callback(status, xhr.response);
					}
				};
				xhr.send();
			};

			function checkStatus () {
				console.log('Executed!');

				getJSON('<?= ($basePath) ?>/ajax/status', function(err, data) {
					if (err !== null) {
						console.log('Something went wrong: ' + err);
						$( "#statusBody" ).removeClass( "bg-success" );
						$( "#statusBody" ).removeClass( "bg-danger" );
						$( "#statusBody" ).addClass( "bg-warning" );
					} else {
						console.log('status: ' + data.result.availability);
						if (data.result.availability == 'Available') {
							$( "#statusBody" ).removeClass( "bg-danger" );
							$( "#statusBody" ).removeClass( "bg-warning" );
							$( "#statusBody" ).addClass( "bg-success" );
						} else {
							$( "#statusBody" ).removeClass( "bg-success" );
							$( "#statusBody" ).removeClass( "bg-warning" );
							$( "#statusBody" ).addClass( "bg-danger" );
						}

					}
				});

			}

			var interval = setInterval(function () {
				checkStatus(); 
			}, 60000);

			checkStatus();

		</script>

		<!-- Optional JavaScript -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
	</body>
</html>
