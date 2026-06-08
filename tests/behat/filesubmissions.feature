@mod @mod_publication @_file_upload
Feature: Keep overview as teacher over submitted files in publication
  In order to support students with their use of student folders
  As a teacher
  I can see who has submitted files in the publication resource folder and which files they have submitted

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |

  @javascript @_file_upload
  Scenario: See the list of students without uploaded files in the student folder
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "publication" activity to course "Course 1" section "1" and I fill the form with:
      | Name          | Test studentfolder name |
      | Description   | Test description        |
      | ID number     | Test studentfolder name |
    And I am on the "Test studentfolder name" activity page logged in as teacher1
    And I navigate to "File submissions" in current page administration
    And I set the field "Filter" to "No file submission"
    Then I should see "Student 1"
    But I should not see "Currently there are no files available or not yet published."
