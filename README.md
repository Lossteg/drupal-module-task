# Drupal 10 Event Management System

## Prerequisites
Before getting started, please ensure you have the following installed on your system:
- PHP 8.1 or higher
- DDEV
- Composer

## Installation and Setup

Follow these steps to set up the project:

1. Start DDEV environment:
   ```bash
   ddev start
   ```
2. Install dependencies:
   ```bash
   ddev composer install
   ```
3. Install Drupal with admin account:
   ```bash
    ddev drush site:install --account-name=admin --account-pass=admin -y
   ```
4. Configure the config sync directory:
   - Open `sites/default/settings.php`
   - Find the line: `# $settings['config_sync_directory'] = '/directory/outside/webroot';`
   - Replace it with: `$settings['config_sync_directory'] = '../config/sync';`
5. Set the site UUID:
   ```bash
   ddev drush cset system.site uuid 5a7c128c-281c-41e0-a257-cabdddd934e7 -y
   ```
6. Remove shortcut sets (to prevent configuration import issues):
   ```bash
    ddev drush entity:delete shortcut_set
   ```
7. Import configuration:
    ```bash
    ddev drush cim -y
   ```
8. Import database:
   ```bash
   ddev drush sql-query --file=../backup.sql
   ```
9. Launch the site:
   ```bash
   ddev launch
   ```

## Testing the Application

### Events Overview

The site includes three pre-configured events that you can explore in the menu tab "Events":
- **Prom**: Should be active but closed for registration (already filled)
- **Pizza Time**: Should be active and open for registration
- **Film Night**: Should be inactive

If **Pizza Time** has already closed at the time of checkout, please activate it manually via the admin panel.

### User Testing

#### Anonymous User
- As an anonymous user, you should see the message "You must be logged in to register for this event" under each event

#### Regular User
1. Log in with the following credentials:
   - Username: `Losteg`
   - Password: `sTroNg_PsWd097`
2. Navigate to events to test registration functionality:
   - "Pizza Time" should allow registration
   - "Prom" should be closed for registration
   - "Film Night" should be finished

#### Administrator
1. Log in with admin credentials:
   - Username: `admin`
   - Password: `admin`
2. Navigate to any event to see the "View Participants" button
3. Administrators can register for events and view participant lists

### Testing Automatic Status Changes

To test the automatic event status update functionality:
1. As an admin, edit any active event and change its date to a past date
2. Run cron job:
   ```bash
   ddev drush cron
3. Refresh the page to confirm the event status has changed

### Creating New Events

When creating new events, ensure the location is formatted correctly:
  - Country: Belarus (in English)
  - City: Mogilev (in English)
  - Address: 64А, ул. Челюскинцев (in Russian, house number first, then street)
  - Region: Mogilev Region (in English, optional for regional centers)

Or you can generate valid Belarus addresses using: https://www.generatormix.com/random-address-in-belarus?state=minsk

## GraphQL API Testing

To test the GraphQL API:
1. Navigate to Administration → Configuration → Web Services → GraphQL
2. Find the server named "EventAPI"
3. Click the dropdown arrow next to "Edit" and select "Explorer"

### Available Queries and Mutations

#### Register for an Event
```graphql
mutation {
  registerForEvent(eventId: 1) {
    success
    message
  }
}
```
#### Get Specific Event Details
```graphql
query {
  event(id: 1) {
    title
    description
    status
    location {
      addressLine1
      locality
      administrativeArea
      countryCode
    }
    coordinates
  }
}
```

#### Get All Active Events
```graphql
query {
  activeEvents {
    id
    title
    startDate
    endDate
    maxParticipants
    location {
      addressLine1
      locality
      administrativeArea
      countryCode
    }
    coordinates
  }
}
```
