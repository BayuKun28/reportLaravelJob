<!-- resources/views/loading.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generating Report</title>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let elapsedTime = 0; // Set elapsed time in seconds
            const timer = document.getElementById('timer');
            const interval = setInterval(function() {
                elapsedTime++;
                timer.textContent = elapsedTime;
            }, 1000);

            const jobId = "{{ $jobId }}";
            const startJob = async () => {
                await fetch(`/reports/start/${jobId}`);
            };
            startJob();

            const checkStatus = setInterval(function() {
                fetch(`/reports/status/${jobId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            clearInterval(checkStatus);
                            window.location.href = data.downloadUrl;
                        } else if (data.status === 'failed') {
                            clearInterval(checkStatus);
                            alert("Failed to generate report. Please try again.");
                        }
                    });
            }, 3000);
        });
    </script>
</head>

<body>
    <h1>Generating your report, please wait...</h1>
    <p>Time elapsed: <span id="timer">0</span> seconds.</p>
</body>

</html>
