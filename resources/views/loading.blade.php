<!-- resources/views/loading.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generating Report</title>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let elapsedTime = 0;
            const timer = document.getElementById('timer');
            let timerInterval = setInterval(function() {
                elapsedTime++;
                timer.textContent = elapsedTime + ' Second';
            }, 1000);

            const hashedJobId = "{{ md5($jobId) }}";
            const startJob = async () => {
                await fetch(`/reports/start/${hashedJobId}`);
            };
            startJob();

            const checkStatus = setInterval(function() {
                fetch(`/reports/status/${hashedJobId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            clearInterval(checkStatus);
                            clearInterval(timerInterval);
                            window.location.href = data.streamUrl;
                        } else if (data.status === 'failed') {
                            clearInterval(checkStatus);
                            clearInterval(timerInterval);
                            timer.textContent = "Gagal";
                            document.getElementById('error-message').textContent = data.errorMessage ||
                                "Failed to generate report. Please try again.";
                            document.getElementById('error-container').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        clearInterval(checkStatus);
                        clearInterval(timerInterval);
                        timer.textContent = "Gagal";
                        document.getElementById('error-message').textContent =
                            "An error occurred. Please try again later.";
                        document.getElementById('error-container').style.display = 'block';
                    });
            }, 3000);
        });
    </script>
</head>

<body>
    <h1>Generating your report, please wait...</h1>
    <p>Time elapsed: <span id="timer">0</span></p>
    <div id="error-container" style="display:none;">
        <p id="error-message" style="color:red;"></p>
    </div>
</body>

</html>
