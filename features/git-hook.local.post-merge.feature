Feature: Test for post-merge hook.

  Background:
    Given I create a "basic" project in "p-01" directory
    And I commit a new "README.md" file with message "Initial commit" and content:
    """
    @todo
    """
    And I run git checkout -b "feature-01"
    And I commit a new "robots.txt" file with message "Add robots.txt" and content:
    """
    Foo
    """
    And I run git checkout "master"

  @hook-post-merge
  Scenario: Normal merge
    Given I run git merge "feature-01" -m "Merge feature-01 into master"
    Then the stdErr should contains the following text:
    """
    ➜  RoboFile::githookPostMerge is called
    ➜  Squash: 0
    """

  @hook-post-merge
  Scenario: Squash merge
    Given I run git merge "feature-01" --squash -m "Merge feature-01 into master"
    Then the stdErr should contains the following text:
    """
    ➜  RoboFile::githookPostMerge is called
    ➜  Squash: 1
    """
