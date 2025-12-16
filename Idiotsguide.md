# `IdiotsGuide.md`

```markdown
# IdiotsGuide  
WP Taxonomy Repair Tool

This is for the version of me who knows something is wrong with categories or tags but cannot quite point to where the bodies are buried.

WordPress does not tell you when taxonomy tables rot.  
This plugin does.

## The three tables you must understand (painless version)

### `wp_terms`
Stores the name and slug.  
Think of this as the label.

### `wp_term_taxonomy`
Stores what type of thing it is (category, tag, custom taxonomy) plus the parent and count.  
Think of this as the role the label plays.

### `wp_term_relationships`
Stores which posts are connected to which taxonomy entries.  
Think of this as the wiring.

If any table goes out of sync with the others, your taxonomy is lying.

## What breaks

### Orphaned Terms  
You have a label (term) with no role (term_taxonomy).  
These do nothing except confuse editors.

### Orphaned Term Taxonomy  
You have a role pointing at a label that no longer exists.  
This is like having a job title with no person assigned to it.

### Ghost Relationships  
You have wires connecting posts to taxonomy entries that do not exist.  
Pure database junk.

### Incorrect Counts  
WordPress stores a cached "count" in `term_taxonomy`.  
Over time these counts become fiction.

### Broken Parents  
Terms claim they have a parent, but that parent was deleted years ago.  
Hierarchy turns into a haunted forest.

### Unknown Taxonomies  
Terms belong to a taxonomy no code registers anymore.  
Leftovers from old plugins or migration mishaps.

### Duplicate Names and Slugs  
Editors see two categories called "News".  
Routing might use the wrong slug. Chaos follows.

## How to use the tool safely

### Step 1  
Open Tools  
Taxonomy Repair.

### Step 2  
Start with orphan terms.  
Safe to delete. They are dead entries with no purpose.

### Step 3  
Delete ghost relationships.  
Harmless and makes future repairs easier.

### Step 4  
Fix incorrect counts.  
Click the repair button. Easy win.

### Step 5  
Review unknown taxonomies.  
Google the taxonomy name.  
If nothing registers it, it is probably leftover debris.

### Step 6  
Look at broken parents.  
Fix manually via the normal taxonomy editor.

### Step 7  
Decide carefully on duplicate names and slugs.  
Changing slugs affects URLs.  
Changing names affects humans.  

Fix only if you have a clear reason.

## Things you should not do

- Do not bulk delete term_taxonomy rows unless you know the taxonomy is dead  
- Do not rename slugs without reading the consequences  
- Do not trust the UI entirely; always verify a few examples manually  
- Do not run this during peak traffic  

## When this tool is most useful

- After migrations  
- After removing plugins  
- Before launching a redesign  
- When categories appear wrong  
- When term counts do not match reality  
- When editors swear something is broken and you cannot see it  

## Final thought

Taxonomy problems do not fix themselves.  
They also rarely explode loudly.  
They just quietly poison navigation, archives, URLs and search.

Running this once every few months keeps the rot under control.

Be gentle. This is surgery, not gardening.
