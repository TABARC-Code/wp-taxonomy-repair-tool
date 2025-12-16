<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code">
</p>

# WP Taxonomy Repair Tool

Taxonomies are where WordPress stores all the structural truth of a site.  
Tags, categories, product attributes, custom vocabularies. All of it lives in three tables that WordPress politely pretends are always tidy.

They are not tidy.

Sites older than two years accumulate:

- orphaned terms  
- term_taxonomy rows with no matching term  
- ghost relationships  
- incorrect counts  
- broken parent hierarchies  
- terms belonging to taxonomies that no longer exist  
- duplicated names and slugs  
- general chaos  

This plugin audits all of it and gives you a clean report.  
Then it hands you repair buttons where safe.

It will not delete anything silently. Nothing automatic. Nothing reckless.

## What it checks

### Orphaned Terms  
Terms in `wp_terms` that have no row in `wp_term_taxonomy`.  
These serve no purpose. Safe to delete.

### Orphaned Term Taxonomy Rows  
`term_taxonomy` entries referencing term IDs that no longer exist.  
These are bad news and often mask deeper corruption.

### Ghost Relationships  
Rows in `wp_term_relationships` linking posts to missing term_taxonomy IDs.  
Pure clutter. Safe to remove.

### Incorrect Counts  
`term_taxonomy.count` lies often.  
Repair buttons recalc the real count from relationships.

### Broken Parent Chains  
Terms whose parent is no longer a valid term.  
Flatten these or fix manually.

### Unknown Taxonomies  
Terms created by plugins long gone.  
Flagged so you can clean carefully.

### Duplicate Names and Slugs  
These confuse editors and occasionally WordPress itself.  
Fix only if you understand the consequences.

## What it does not do

- It does not auto repair anything  
- It does not rewrite term slugs  
- It does not merge terms  
- It does not guess what relationships should exist  
- It does not try to be clever  

It audits. You decide what to fix.

## Installation

```bash
git clone https://github.com/TABARC-Code/wp-taxonomy-repair-tool.git
Drop into:

text
Copy code
wp-content/plugins/wp-taxonomy-repair-tool
Activate, then visit:

nginx
Copy code
Tools  
Taxonomy Repair
Safe workflow
1. Review Orphan Terms
Safe to remove. They belong to nothing.

2. Review Ghost Relationships
Click “delete ghost relationships”.
Harmless cleanup.

3. Fix incorrect counts
Click “repair count” for any lying taxonomy rows.
Fixes inconsistent term counts.

4. Review orphan term_tax rows
Handle manually. These are serious and often require DB-level cleanup.

5. Handle unknown taxonomies
Usually leftovers from plugins removed years ago.

6. Review duplicates
Fix manually via slug updates or merges.

Roadmap
Possible future additions:

- JSON export of audit
- Batch mode for huge multisite setups
- Automatic hierarchy repairs
- Term merge helpers
- Unlikely to ever happen:
-  Fully automatic repair (too risky)
-  Slug rewriting automation
-  Anything that guesses intent

1# wp-taxonomy-repair-too
Description: Audits and repairs taxonomy issues WordPress quietly ignores. Orphaned terms, mismatched counts, broken parent chains, ghost relationships and taxonomies left behind by long dead plugins.  As we all suffer from this crap
