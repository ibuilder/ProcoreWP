# Procore Integration for WordPress

Connect your WordPress site to the Procore construction management platform using this integration plugin. Display project information, team members, drawings, specifications, and more directly on your WordPress site using simple shortcodes.

## Features

- **Easy Authentication**: Connect to Procore API with your Client ID and Client Secret
- **Project Information**: Display comprehensive project details
- **Team Members**: List project team members and their roles
- **Drawings & Specifications**: Show project drawings and specifications
- **Featured Images**: Display project images
- **Custom Data Fields**: Access any project data field using shortcodes
- **Responsive Design**: Works with any WordPress theme

## Installation

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New
4. Click "Upload Plugin" and select the ZIP file
5. Click "Install Now" and then "Activate Plugin"

## Configuration

1. Go to Settings > Procore Integration
2. Enter your Procore API credentials:
   - **Client ID**: Your Procore API client ID
   - **Client Secret**: Your Procore API client secret
   - **API URL**: Default is https://api.procore.com (usually doesn't need to be changed)
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

### Project Information

```
[procore_project id="123"]
```

Displays basic project information including name, address, start date, completion date, and status.

### Team Members

```
[procore_team id="123"]
```

Lists all team members assigned to the project with their names, emails, and roles.

### Featured Image

```
[procore_featured_image id="123" width="400" height="auto"]
```

Displays the project's featured image or logo.

**Parameters:**
- `width`: Image width in pixels (default: 300)
- `height`: Image height in pixels (default: auto)

### Drawings

```
[procore_drawings id="123" limit="5"]
```

Lists project drawings with their names and descriptions.

**Parameters:**
- `limit`: Maximum number of drawings to display (default: 10)

### Specifications

```
[procore_specifications id="123" limit="5"]
```

Lists project specifications with their numbers, titles, and descriptions.

**Parameters:**
- `limit`: Maximum number of specifications to display (default: 10)

### Custom Project Data

```
[procore_project_data id="123" field="budget" label="Project Budget"]
```

Displays a specific project data field.

**Parameters:**
- `field`: The API field name to display (required)
- `label`: Custom label for the field (optional, defaults to formatted field name)

## Example Page Layout

Here's an example of how you might use multiple shortcodes on a single page:

```
<h1>Project Overview</h1>

[procore_featured_image id="123" width="600"]

[procore_project id="123"]

<h2>Key Information</h2>

<div class="project-data-grid">
  [procore_project_data id="123" field="budget" label="Budget"]
  [procore_project_data id="123" field="square_feet" label="Square Footage"]
  [procore_project_data id="123" field="project_number" label="Project Number"]
</div>

<h2>Project Team</h2>

[procore_team id="123"]

<h2>Project Drawings</h2>

[procore_drawings id="123" limit="5"]

<h2>Project Specifications</h2>

[procore_specifications id="123" limit="5"]
```

## Styling

The plugin includes basic CSS styling for all shortcode outputs. You can customize the appearance by adding custom CSS to your theme or using a custom CSS plugin.

The main CSS classes used by the plugin are:

- `.procore-project`: Main container for project information
- `.procore-project-details`: Container for project details
- `.procore-team-list`: List of team members
- `.procore-team-member`: Individual team member container
- `.procore-drawings-list`: List of drawings
- `.procore-drawing`: Individual drawing container
- `.procore-specifications-list`: List of specifications
- `.procore-specification`: Individual specification container
- `.procore-project-image`: Container for project image
- `.procore-project-data`: Container for custom project data
- `.procore-data-label`: Label for custom project data
- `.procore-data-value`: Value for custom project data

## Troubleshooting

### Common Issues

1. **Connection Failed**: Make sure your Client ID and Client Secret are correct. Check that your Procore account has the necessary permissions.

2. **No Data Displayed**: Ensure you're using the correct project ID in your shortcodes. Project IDs can be found in the URL when viewing a project in Procore (e.g., `https://app.procore.com/projects/123/...`).

3. **Drawings or Specifications Not Showing**: Not all projects have drawings or specifications. Check that these exist in your Procore project.

4. **API Rate Limiting**: Procore may limit the number of API requests. If you're displaying many shortcodes on a single page, consider caching the data.

### Support

If you encounter issues, check the following:

1. WordPress error logs
2. Procore API documentation at [developers.procore.com](https://developers.procore.com/documentation)
3. Contact the plugin developer for support

## Advanced Usage

### Caching

For better performance, consider implementing a caching solution for API responses, especially if you have many Procore projects or many users viewing project data. The plugin doesn't include caching by default.

### Custom Templates

You can override the default HTML output by creating custom template files in your theme. More information on this can be found in the plugin documentation.

## Changelog

### Version 1.0.0
- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name/Company]

## Privacy

This plugin connects to the Procore API and sends/receives data from Procore's servers. No data is shared with any third party. Please review Procore's privacy policy for information on how they handle your data.
