# Procore Integration for WordPress

Connect your WordPress site to the Procore construction management platform using this integration plugin. Display project information, team members, drawings, specifications, and more directly on your WordPress site using simple shortcodes.

## Features

- **Easy Authentication**: Connect to Procore API with your Client ID and Client Secret
- **Multi-Company Support**: Specify company ID in each shortcode or set a default
- **Project Information**: Display comprehensive project details
- **Team Members**: List project team members and their roles
- **Drawings & Specifications**: Show project drawings and specifications
- **Featured Images**: Display project images
- **Custom Data Fields**: Access any project data field using shortcodes
- **Responsive Design**: Works with any WordPress theme
- **Customizable Styles**: Easily modify CSS styles to match your theme

## Installation

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New
4. Click "Upload Plugin" and select the ZIP file
5. Click "Install Now" and then "Activate Plugin"

## File Structure

The plugin is organized with a clean, modular structure:

```
procore-integration/
├── index.php                       # Main plugin file
├── includes/
│   ├── styles.php                  # Handles CSS loading
│   └── activation.php              # Plugin activation hooks
├── assets/
│   └── css/
│       ├── procore-integration.css             # Active CSS file (customizable)
│       └── procore-integration-default.css     # Default CSS template
└── README.md                       # Documentation
```

## Configuration

1. Go to Settings > Procore Integration
2. Enter your Procore API credentials:
   - **Client ID**: Your Procore API client ID
   - **Client Secret**: Your Procore API client secret
   - **API URL**: Default is https://api.procore.com (usually doesn't need to be changed)
   - **Default Company ID**: Your default Procore company ID (used when not specified in shortcodes)
3. Click "Save Changes"
4. Click "Test Connection" to verify your credentials work correctly

## Getting Procore API Credentials

To use this plugin, you'll need to create an application in the Procore Developer Portal:

1. Go to [Procore Developer Portal](https://developers.procore.com/)
2. Sign in with your Procore account
3. Navigate to "My Apps" and click "New App"
4. Fill in the required information:
   - **Name**: Your app name (e.g., "WordPress Integration")
   - **Redirect URI**: Your site URL (e.g., https://example.com/wp-admin/options-general.php?page=procore-integration)
   - **Permissions**: Select the permissions you need (at minimum: projects, users, documents)
5. Click "Create" to generate your Client ID and Client Secret
6. Copy these credentials to your WordPress plugin settings

## Using Shortcodes

### Project List

```
[procore_project_list company_id="123" limit="10" show_details="true" active_only="true" sort_by="name" sort_order="asc"]
```

Displays a list of all available Procore projects with their IDs and names.

**Parameters:**
- `company_id`: The Procore company ID (optional if default set in settings)
- `limit`: Maximum number of projects to display (default: 0, shows all)
- `show_details`: Whether to show additional details like location and status (default: false)
- `active_only`: Whether to show only active projects (default: true)
- `sort_by`: Field to sort by - "name", "id", or "created_at" (default: "name")
- `sort_order`: Sort order - "asc" or "desc" (default: "asc")

### Project Information

```
[procore_project id="123" company_id="123"]
```

Displays basic project information including name, address, start date, completion date, and status.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)

### Team Members

```
[procore_team id="123" company_id="123"]
```

Lists all team members assigned to the project with their names, emails, and roles.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)

### Featured Image

```
[procore_featured_image id="123" company_id="123" width="400" height="auto"]
```

Displays the project's featured image or logo.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)
- `width`: Image width in pixels (default: 300)
- `height`: Image height in pixels (default: auto)

### Drawings

```
[procore_drawings id="123" company_id="123" limit="5"]
```

Lists project drawings with their names and descriptions.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)
- `limit`: Maximum number of drawings to display (default: 10)

### Specifications

```
[procore_specifications id="123" company_id="123" limit="5"]
```

Lists project specifications with their numbers, titles, and descriptions.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)
- `limit`: Maximum number of specifications to display (default: 10)

### Custom Project Data

```
[procore_project_data id="123" company_id="123" field="budget" label="Project Budget"]
```

Displays a specific project data field.

**Parameters:**
- `id`: The Procore project ID (required)
- `company_id`: The Procore company ID (optional if default set in settings)
- `field`: The API field name to display (required)
- `label`: Custom label for the field (optional, defaults to formatted field name)

## Example Page Layout

Here's an example of how you might use multiple shortcodes on a single page:

```
<h1>Project Overview</h1>

[procore_featured_image id="123" company_id="456" width="600"]

[procore_project id="123" company_id="456"]

<h2>Key Information</h2>

<div class="project-data-grid">
  [procore_project_data id="123" company_id="456" field="budget" label="Budget"]
  [procore_project_data id="123" company_id="456" field="square_feet" label="Square Footage"]
  [procore_project_data id="123" company_id="456" field="project_number" label="Project Number"]
</div>

<h2>Project Team</h2>

[procore_team id="123" company_id="456"]

<h2>Project Drawings</h2>

[procore_drawings id="123" company_id="456" limit="5"]

<h2>Project Specifications</h2>

[procore_specifications id="123" company_id="456" limit="5"]
```

And here's an example of a Projects Directory page using the project list shortcode:

```
<h1>Procore Projects Directory</h1>

<p>Below is a list of all our current active projects in Procore:</p>

[procore_project_list company_id="456" show_details="true" active_only="true" sort_by="name"]

<p>Click on a project ID to view more details about that specific project.</p>
```

## Customizing Styles

The plugin includes default CSS styles that you can customize to match your theme:

1. Navigate to the plugin directory in your WordPress installation: `/wp-content/plugins/procore-integration/`
2. Edit the file at `assets/css/procore-integration.css`
3. Save your changes

If you need to reset to the default styles, you can copy the contents from `assets/css/procore-integration-default.css` into your active CSS file.

## Finding Your Company ID

To locate your Procore Company ID:

1. Log in to your Procore account
2. Look at the URL in your browser when viewing your company dashboard
3. The URL will contain a pattern like `https://app.procore.com/companies/XXXX/...` where `XXXX` is your company ID
4. Alternatively, you can use the [procore_project_list] shortcode without a company ID first, and the API error message may include information about available company IDs

You can set a default Company ID in the plugin settings page to avoid having to specify it in every shortcode.

## Troubleshooting

### Common Issues

1. **Connection Failed**: Make sure your Client ID and Client Secret are correct. Check that your Procore account has the necessary permissions.

2. **No Data Displayed**: Ensure you're using the correct project ID and company ID in your shortcodes. Project IDs can be found in the URL when viewing a project in Procore (e.g., `https://app.procore.com/projects/123/...`).

3. **Company ID Error**: If you're getting errors about company_id, make sure you're either specifying the correct company ID in each shortcode or have set a default company ID in the plugin settings.

4. **Drawings or Specifications Not Showing**: Not all projects have drawings or specifications. Check that these exist in your Procore project.

5. **API Rate Limiting**: Procore may limit the number of API requests. If you're displaying many shortcodes on a single page, consider caching the data.

### Support

If you encounter issues, check the following:

1. WordPress error logs
2. Procore API documentation at [developers.procore.com](https://developers.procore.com/documentation)
3. Contact the plugin developer for support

## Changelog

### Version 1.0.0
- Initial release with multi-company support
- Separated CSS into a dedicated file for easier customization
- Added modular file structure for better maintainability

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name/Company]

## Privacy

This plugin connects to the Procore API and sends/receives data from Procore's servers. No data is shared with any third party. Please review Procore's privacy policy for information on how they handle your data.