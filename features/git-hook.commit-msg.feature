Feature: Test for commit-msg hook.

  Scenario Outline: commit-msg - positive and negative
    When I create a "basic" project in "p-01" directory
    And I create a "README.md" file
    And I run git add "README.md"
    And I run git commit -m "<message>"
    Then the exit code should be <exit_code>
    And the stdErr should contains the following text:
    """
    >  RoboFile::githookCommitMsg is called
    >  File name: '.git/COMMIT_EDITMSG'
    """
    Examples:
      | message            | exit_code |
      | Valid              | 0         |
      | Invalid commit-msg | 1         |
