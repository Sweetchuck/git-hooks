Feature: Test for prepare-commit-msg hook.

  Background:
    Given I create a "basic" project in "p-01" directory
    And I create a "README.md" file
    And I run git add "README.md"

  Scenario: Commit with commit message.
    Given I run git commit -m "Initial commit"
    Then the exit code should be 0
    And the stdErr should contains the following text:
    """
    >  RoboFile::githookPrepareCommitMsg is called
    >  File name: '.git/COMMIT_EDITMSG'
    >  Description: 'message'
    """

  Scenario Outline: Commit without commit message.
    Given I run git config core.editor <editor>
    And I run git commit
    Then the exit code should be <exit_code>
    And the stdErr should contains the following text:
    """
    >  RoboFile::githookPrepareCommitMsg is called
    >  File name: '.git/COMMIT_EDITMSG'
    >  Description: ''
    """
    Examples:
      | editor | exit_code |
      | true   | 0         |
      | false  | 1         |
