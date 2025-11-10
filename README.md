# unique-codes-download
Claude AI generated WordPress plugin to create unique codes and use them to download a file. One code per download.

## Installation

1. Create Plugin File

Navigate to plugins directory
`cd /var/www/wordpress/wp-content/plugins/`

Create plugin directory
`sudo mkdir secure-download-system`
`cd secure-download-system`

Create the plugin file
sudo nano secure-download-system.php

2. Copy Plugin Code

Copy the entire plugin code from the .php file in the repo.
Paste it into secure-download-system.php
Save and exit.

3. Set Permissions
```
sudo chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/secure-download-system
sudo chmod 755 /var/www/wordpress/wp-content/plugins/secure-download-system
sudo chmod 644 /var/www/wordpress/wp-content/plugins/secure-download-system/secure-download-system.php
```

4. Activate Plugin

Go to WordPress Admin → Plugins
Find "Secure Code Download System"
Click Activate

## Setup download file

### 1. Create Secure Directory
```
cd /var/www/wordpress/wp-content/uploads/
sudo mkdir secure-files
cd secure-files
```

### 2. Add Security File

Create .htaccess to block direct access

`echo "Deny from all" | sudo tee .htaccess`

Set permissions
`sudo chmod 644 .htaccess`

Upload your .zip file to the `secure-files` directory.

### 3. Set file permissions

```
sudo chmod 644 /var/www/wordpress/wp-content/uploads/secure-files/download.zip
sudo chown www-data:www-data /var/www/wordpress/wp-content/uploads/secure-files/download.zip
```

## Implementation

Edit a page in Elementor and use the shortcode widget. The shortcode is `[download_form]`


## Using the plugin

### 1. Access Admin Panel

Go to WordPress Admin → Download Codes (in left sidebar)

### 2. Verify Setup Checklist

Check that all items show ✅:

✅ Database table created  
✅ Secure directory exists  
✅ Download file exists (should show file size, e.g., "150 MB")  
✅ Security file (.htaccess) exists  

If any show ❌:

Use the "Reset Database Table" button in Danger Zone.

### 3. Generate Codes

Scroll to "⚙️ Generate Codes" section
Enter number of codes (default: 500, max: 5000)
Click "Generate Codes"
Wait for confirmation: "Generated X codes!"

Codes are:

6 characters long (e.g., A3K7M9)
Uppercase letters + numbers
No confusing characters (0/O, 1/I removed)
Unique and randomly generated

### 4. Export Codes

Scroll to "📥 Export Codes" section
Click "Download Unused Codes (CSV)"
CSV file downloads with format:

```csv
   Download Code
   A3K7M9
   P5W2Q8
   Z4H6R3
```

## Behaviour

1. Single use - Each code can only be used by 1 person
2. 15-minute grace period - User can retry download 3 times within 15 minutes if it fails
3. IP-locked - Only the person who first used the code can retry
4. Attempts tracked - Maximum 3 download attempts per code

## Customization
### Style the Download Form
#### Option A: Use Plugin CSS Editor

1. Scroll to "🎨 Custom CSS Styling"  
2. Edit CSS in the textarea  
3. Click "Save Custom CSS"  
4. Preview on your download page  

#### Option B: Use Elementor CSS

1. Check ✅ "Disable plugin CSS"
2. Click "Save Custom CSS"
3. Style the form in Elementor's Custom CSS panel
4. No conflicts with plugin styles

CSS Classes Available:

`.sds-download-form` - Main container  
`.sds-download-form h3` - Heading  
`.sds-download-form input[type="text"]` - Input field  
`.sds-download-form button` - Submit button  
`.sds-message.error` - Error messages  
`.sds-message.success` - Success messages  

## Maintenance & Troubleshooting

### Delete All Codes (Keep Table Structure)

1. Go to "⚠️ Danger Zone"
2. Click "🗑️ Delete All Codes"
3. Confirm the warning

All codes deleted, table structure intact  
Generate new codes as needed

### Reset Database (Fix Structure Issues)

1. Go to "⚠️ Danger Zone"
2. Click "🔄 Reset Database Table"
3. Confirm the warning

Table dropped and recreated with correct structure  
All codes deleted - generate new ones

## Use Reset When:

Table wasn't created during plugin activation
Upgrading from old plugin version
Database structure is corrupted
Getting persistent errors

## Check File Setup

File path shown in setup checklist
File size displayed when detected
Must be named exactly: download.zip
Must be in: /wp-content/uploads/secure-files/
