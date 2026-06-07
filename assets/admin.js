jQuery(function ($) {
    'use strict';

    let jobId = null;
    let currentOffset = 0;
    let totalRows = 0;
    let isRunning = false;

    const $form = $('#srkpics-alt-upload-form');
    const $message = $('#srkpics-upload-message');
    const $progressCard = $('#srkpics-progress-card');
    const $log = $('#srkpics-live-log');
    const $progressBar = $('#srkpics-progress-bar');
    const $startBtn = $('#srkpics-start-import');
    const $downloadFailed = $('#srkpics-download-failed');

    function addLog(message) {
        const time = new Date().toLocaleTimeString();
        $log.prepend('<div>[' + time + '] ' + $('<div>').text(message).html() + '</div>');
    }

    function setMessage(message, type) {
        $message.removeClass('notice-success notice-error').addClass(type === 'error' ? 'notice-error' : 'notice-success');
        $message.html('<p>' + $('<div>').text(message).html() + '</p>').show();
    }

    function updateStats(data) {
        $('#stat-total').text(data.total || totalRows || 0);
        $('#stat-processed').text(data.processed || 0);
        $('#stat-updated').text(data.updated || 0);
        $('#stat-skipped').text(data.skipped || 0);
        $('#stat-failed').text(data.failed || 0);

        const total = parseInt(data.total || totalRows || 0, 10);
        const processed = parseInt(data.processed || 0, 10);
        const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
        $progressBar.css('width', percent + '%').text(percent + '%');
    }

    $form.on('submit', function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('srkpics_alt_file');
        if (!fileInput || !fileInput.files.length) {
            setMessage('Please choose a CSV or XLSX file first.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'srkpics_alt_prepare_import');
        formData.append('nonce', SRKPICSAltImporter.nonce);
        formData.append('import_file', fileInput.files[0]);
        formData.append('skip_existing', $('#skip_existing').is(':checked') ? '1' : '');
        formData.append('dry_run', $('#dry_run').is(':checked') ? '1' : '');

        $('#srkpics-alt-upload-btn').prop('disabled', true).text('Uploading...');
        $message.hide();
        $log.empty();
        $downloadFailed.addClass('srkpics-hidden');

        $.ajax({
            url: SRKPICSAltImporter.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            cache: false,
            success: function (response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Upload failed.';
                    setMessage(msg, 'error');
                    return;
                }

                jobId = response.data.job_id;
                currentOffset = 0;
                totalRows = response.data.total;

                setMessage(response.data.message, 'success');
                $progressCard.removeClass('srkpics-hidden');
                updateStats({ total: totalRows, processed: 0, updated: 0, skipped: 0, failed: 0 });
                addLog('File prepared. Click Start Live Import.');
            },
            error: function (xhr) {
                setMessage('AJAX upload failed. Check browser console or server error log.', 'error');
                console.error(xhr.responseText);
            },
            complete: function () {
                $('#srkpics-alt-upload-btn').prop('disabled', false).text('Upload and Prepare Import');
            }
        });
    });

    $startBtn.on('click', function () {
        if (!jobId) {
            setMessage('Please upload and prepare a file first.', 'error');
            return;
        }

        if (isRunning) {
            return;
        }

        isRunning = true;
        $startBtn.prop('disabled', true).text('Import Running...');
        addLog('Live AJAX import started.');
        processBatch();
    });

    function processBatch() {
        $.ajax({
            url: SRKPICSAltImporter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'srkpics_alt_process_batch',
                nonce: SRKPICSAltImporter.nonce,
                job_id: jobId,
                offset: currentOffset,
                batch_size: SRKPICSAltImporter.batchSize || 50
            },
            success: function (response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Batch failed.';
                    addLog('ERROR: ' + msg);
                    setMessage(msg, 'error');
                    isRunning = false;
                    $startBtn.prop('disabled', false).text('Resume Import');
                    return;
                }

                const data = response.data;
                currentOffset = data.offset;
                updateStats(data);

                if (data.logs && data.logs.length) {
                    data.logs.forEach(function (item) {
                        addLog(item);
                    });
                }

                if (data.has_failed) {
                    const downloadUrl = SRKPICSAltImporter.ajaxUrl + '?action=srkpics_alt_download_failed&job_id=' + encodeURIComponent(jobId) + '&nonce=' + encodeURIComponent(SRKPICSAltImporter.nonce);
                    $downloadFailed.attr('href', downloadUrl).removeClass('srkpics-hidden');
                }

                if (data.done) {
                    addLog('Import completed.');
                    setMessage('Import completed successfully.', 'success');
                    isRunning = false;
                    $startBtn.prop('disabled', false).text('Import Completed');
                    return;
                }

                setTimeout(processBatch, 250);
            },
            error: function (xhr) {
                addLog('AJAX batch failed. Check browser console or server error log.');
                console.error(xhr.responseText);
                setMessage('AJAX batch failed. Check browser console or server error log.', 'error');
                isRunning = false;
                $startBtn.prop('disabled', false).text('Resume Import');
            }
        });
    }
});
