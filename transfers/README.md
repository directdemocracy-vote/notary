This folder contains temporary empty files whose names are fingerprints of citizen card being transferred.
They are created by transferred.php when displaying the transfer QR code.
They are deleted by publish.php when the corresponding transferred report certificate is published.
They are monitored by tranferred.php (long lasting request) to detect when a transfer is complete.
If the file is not deleted by publish.php within 1 minute, then it is deleted by transferred.php.
