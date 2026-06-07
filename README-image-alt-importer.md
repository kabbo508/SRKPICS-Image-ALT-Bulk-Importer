# SRKPICS Image ALT Bulk Importer

Bulk import WordPress Media Library image ALT text from CSV or XLSX files with live AJAX batch processing.

## Description

SRKPICS Image ALT Bulk Importer helps WordPress administrators update image ALT text in bulk without manually editing each image in the Media Library.

Upload a CSV or XLSX file with image URLs and suggested ALT tags, prepare the import, then run the live AJAX importer. The plugin processes images batch by batch and displays real-time progress, logs, updated counts, skipped counts, and failed rows.

## Features

- Bulk import image ALT text
- CSV and XLSX file support
- Live AJAX batch processing
- Real-time progress bar
- Live import log
- Updated, skipped, and failed counters
- Dry run mode for testing
- Option to skip images that already have ALT text
- Download failed rows as CSV
- Supports resized image URLs such as `image-500x750.jpg`
- Matches images by full URL, original filename, and Media Library attachment data

## Required File Columns

| Column | Required | Description |
| --- | --- | --- |
| Image URL | Yes | Full image URL from the website |
| Suggested ALT Tag | Yes | ALT text to import for the image |
| Source URLs | No | Page or product URL where the image appears |

## Accepted Column Names

### Image URL

- Image URL
- Image
- URL
- Image Link

### ALT Text

- Suggested ALT Tag
- ALT Tag
- ALT Text
- Image ALT
- ALT

### Source URL

- Source URLs
- Source URL
- Page URL
- Page

## Sample CSV Format

```csv
Image URL,Suggested ALT Tag,Source URLs
https://yourwebsite.com/wp-content/uploads/2026/01/blue-shirt.jpg,Blue shirt for men,https://yourwebsite.com/product/blue-shirt/
https://yourwebsite.com/wp-content/uploads/2026/01/red-dress.jpg,Elegant red dress for women,https://yourwebsite.com/product/red-dress/
```

## How to Create CSV File

1. Open Microsoft Excel or Google Sheets.
2. Create these columns:
   - Image URL
   - Suggested ALT Tag
   - Source URLs
3. Add your image URLs and ALT text.
4. Save or export the file as **CSV UTF-8 (Comma Delimited)**.
5. Upload the CSV file inside the plugin importer.

## Usage

1. Install and activate the plugin.
2. Go to **Image ALT Importer** in the WordPress admin menu.
3. Upload your CSV or XLSX file.
4. Select optional settings:
   - Skip images that already have ALT text
   - Dry run only
5. Click **Upload and Prepare Import**.
6. Click **Start Live Import**.
7. Review the live log and final results.
8. Download failed rows if any images were not found.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- PHP ZipArchive enabled for XLSX import

## Changelog

### 2.2.0

- Fixed false missing column error when `Image URL` is the first CSV column.
- Added CSV UTF-8 BOM cleanup.
- Improved image matching for resized image URLs.
- Added live AJAX batch import.
- Added failed rows CSV export.

## Author

SRKPICS

## License

GPL v2 or later
