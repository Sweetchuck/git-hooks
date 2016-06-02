Feature: Test for pre-commit hook.

  @hook-pre-commit
  Scenario Outline: Positive & negative.
    Given I create a "basic" project in "p-01" directory
    And I create a <file_name> file
    And I run git add <file_name>
    And I run git commit -m "Initial commit"
    Then the stdErr should contains the following text:
    """
    RoboFile::githookPreCommit is called
    """
    And the number of commits is <commits>
    Examples:
      | file_name | commits |
      | true.txt  | 1       |
      | false.txt | 0       |
