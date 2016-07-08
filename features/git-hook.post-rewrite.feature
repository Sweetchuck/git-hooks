Feature: Test for post-rewrite hook.

  Background:
    Given I create a "basic" project in "p-01" directory
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """
    And I run git checkout -b "production"
    And I run git branch "feature-1"
    And I commit a new "foo.txt" file with message "Add foo.txt" and content:
    """
    @todo
    """
    And I run git checkout "feature-1"
    And I commit a new "bar.txt" file with message "Add bar.txt" and content:
    """
    @todo
    """

  Scenario: Current branch
    And I run git rebase "production"
    Then the exit code should be 0
    And the stdErr should contains the following text:
    """
    >  RoboFile::githookPostRewrite is called
    >  Trigger: "rebase"
    >  stdInput line 1: "OLD_REV" "NEW_REV" ""
    >  Lines in stdInput: "1"
    """

  Scenario: Other branch
    And I run git checkout "master"
    And I run git rebase "production" "feature-1"
    Then the exit code should be 0
    And the stdErr should contains the following text:
    """
    >  RoboFile::githookPostRewrite is called
    >  Trigger: "rebase"
    >  stdInput line 1: "OLD_REV" "NEW_REV" ""
    >  Lines in stdInput: "1"
    """
