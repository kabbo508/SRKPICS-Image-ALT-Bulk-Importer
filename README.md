# SRKPICS Image ALT Bulk Importer

Bulk import WordPress Media Library image ALT text from CSV or XLSX files with live AJAX batch processing.

## CSV Format

```csv
Image URL,Suggested ALT Tag,Source URLs
https://example.com/wp-content/uploads/image.jpg,Elegant red dress for women,https://example.com/product/red-dress/
```

## Version 2.2.0 Fixes

- Fixed false missing column error when Image URL is the first CSV column.
- Uses `isset()` instead of `empty()` for header index detection.
- Added BOM cleanup for CSV UTF-8 headers.
- Improved image matching for resized images like `image-500x750.jpg`.

## Features

- CSV and XLSX upload
- Live AJAX import
- Progress bar
- Live import log
- Updated, skipped, failed counters
- Skip existing ALT text option
- Dry run option
- Failed rows CSV export
