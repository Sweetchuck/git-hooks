Feature: Test for post-commit hook.

  Scenario: Trigger the post-commit hook.
    Given I create a "basic" project in "p-01" directory
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """
    Then the stdErr should contains the following text:
    """
    >  RoboFile::githookPostCommit is called
    """
