<?php
/**
 * Plugin Name: WP Taxonomy Repair Tool
 * Plugin URI: https://github.com/TABARC-Code/wp-taxonomy-repair-tool
 * Description: Audits and repairs taxonomy issues WordPress quietly ignores. Orphaned terms, mismatched counts, broken parent chains, ghost relationships and taxonomies left behind by long dead plugins.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Why this exists:
 * Over years of edits, imports, plugin churn and panicked midnight fixes, the taxonomy tables turn into a crime scene.
 * Terms that point at nothing. Term taxonomy rows with counts that lie. Relationships pointing at ghosts. Parents missing.
 * WordPress shrugs and pretends everything is fine. It is not.
 *
 * This plugin audits the taxonomy system and offers safe repair buttons for:
 * - Orphaned terms (terms with no corresponding term_taxonomy row)
 * - Orphaned term_taxonomy rows (no matching term entry)
 * - Ghost term relationships (relationships pointing at missing posts or taxonomies)
 * - Mismatched counts (repair counts to truth)
 * - Missing parent terms
 * - Terms belonging to taxonomies no longer registered
 * - Duplicate term names and slugs that confuse editors
 *
 * It does not auto delete anything. Nothing behind your back.
 * You click to repair, and only the safe repairs run.
 *
 * TODO: add JSON export for audit results.
 * TODO: add a batch mode for large sites with thousands of terms.
 * FIXME: Some taxonomies do creative things with hierarchy. This version assumes normal behaviour.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Taxonomy_Repair_Tool {

    private $screen_slug = 'wp-taxonomy-repair-tool';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
        add_action( 'admin_post_wptax_fix_count', array( $this, 'handle_fix_count' ) );
        add_action( 'admin_post_wptax_delete_orphan', array( $this, 'handle_delete_orphan' ) );
        add_action( 'admin_post_wptax_delete_ghost_relationships', array( $this, 'handle_delete_ghost_relationships' ) );
    }

    private function get_icon_url() {
        return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
    }

    public function add_tools_page() {
        add_management_page(
            'Taxonomy Repair Tool',
            'Taxonomy Repair',
            'manage_options',
            $this->screen_slug,
            array( $this, 'render_screen' )
        );
    }

    public function render_screen() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        $audit = $this->run_audit();

        ?>
        <div class="wrap">
            <h1>Taxonomy Repair Tool</h1>
            <p>
                WordPress taxonomies accumulate damage over time.
                This tool audits the term, term_taxonomy and term_relationships tables and tries to make sense of the mess.
            </p>

            <h2>Summary</h2>
            <?php $this->render_summary_box( $audit ); ?>

            <h2>Orphaned Terms</h2>
            <p>
                These terms exist in wp_terms but have no corresponding row in wp_term_taxonomy.
                They serve no purpose and can be safely removed.
            </p>
            <?php $this->render_orphan_terms( $audit ); ?>

            <h2>Orphaned Term Taxonomy Rows</h2>
            <p>
                These rows in wp_term_taxonomy point at term_ids that no longer exist.
                They produce broken relationships and must be cleaned manually with caution.
            </p>
            <?php $this->render_orphan_term_tax( $audit ); ?>

            <h2>Ghost Relationships</h2>
            <p>
                These relationships link posts to missing term_taxonomy rows.
                They are harmless clutter, but can be removed for sanity.
            </p>
            <?php $this->render_ghost_relationships( $audit ); ?>

            <h2>Incorrect Term Counts</h2>
            <p>
                Term_taxonomy counts often lie due to old imports or manual DB edits.
                The repair button recalculates the real count.
            </p>
            <?php $this->render_incorrect_counts( $audit ); ?>

            <h2>Broken Parent Chains</h2>
            <p>
                These terms claim a parent that does not exist.
                They can be reassigned or flattened manually.
            </p>
            <?php $this->render_broken_parents( $audit ); ?>

            <h2>Terms in Unregistered Taxonomies</h2>
            <p>
                These terms belong to taxonomies that no plugin currently registers.
                Often leftovers from plugins removed years ago.
            </p>
            <?php $this->render_unknown_taxonomies( $audit ); ?>

            <h2>Duplicate Names and Slugs</h2>
            <p>
                These confuse editors and sometimes routing.
                Handle with caution. Changing slugs affects URLs.
            </p>
            <?php $this->render_duplicates( $audit ); ?>

        </div>
        <?php
    }

    /**
     * Run all audit checks and return structured report.
     */
    private function run_audit() {
        global $wpdb;

        $terms             = $wpdb->get_results( "SELECT * FROM $wpdb->terms" );
        $term_tax          = $wpdb->get_results( "SELECT * FROM $wpdb->term_taxonomy" );
        $relationships     = $wpdb->get_results( "SELECT * FROM $wpdb->term_relationships" );

        $registered_tax    = get_taxonomies();

        $term_map          = array();
        $term_tax_map      = array();
        $term_tax_by_term  = array();
        $relationships_map = array();

        foreach ( $terms as $t ) {
            $term_map[ $t->term_id ] = $t;
        }

        foreach ( $term_tax as $tx ) {
            $term_tax_map[ $tx->term_taxonomy_id ] = $tx;
            $term_tax_by_term[ $tx->term_id ][] = $tx;
        }

        foreach ( $relationships as $rel ) {
            $relationships_map[] = $rel;
        }

        // Orphaned terms
        $orphan_terms = array();
        foreach ( $terms as $t ) {
            if ( empty( $term_tax_by_term[ $t->term_id ] ) ) {
                $orphan_terms[] = $t;
            }
        }

        // Orphaned term taxonomy rows
        $orphan_term_tax = array();
        foreach ( $term_tax as $tx ) {
            if ( ! isset( $term_map[ $tx->term_id ] ) ) {
                $orphan_term_tax[] = $tx;
            }
        }

        // Ghost relationships
        $ghost = array();
        foreach ( $relationships_map as $rel ) {
            if ( ! isset( $term_tax_map[ $rel->term_taxonomy_id ] ) ) {
                $ghost[] = $rel;
            }
        }

        // Incorrect counts
        $incorrect_counts = array();
        foreach ( $term_tax as $tx ) {
            $real_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d",
                    $tx->term_taxonomy_id
                )
            );
            if ( (int) $real_count !== (int) $tx->count ) {
                $incorrect_counts[] = array(
                    'tx'        => $tx,
                    'real'      => (int) $real_count,
                    'stored'    => (int) $tx->count,
                );
            }
        }

        // Broken parent chains
        $broken_parents = array();
        foreach ( $term_tax as $tx ) {
            if ( $tx->parent && ! isset( $term_tax_by_term[ $tx->parent ] ) ) {
                $broken_parents[] = $tx;
            }
        }

        // Unknown taxonomies
        $unknown_taxonomies = array();
        foreach ( $term_tax as $tx ) {
            if ( ! in_array( $tx->taxonomy, $registered_tax, true ) ) {
                $unknown_taxonomies[] = $tx;
            }
        }

        // Duplicate names and slugs
        $duplicate_names = array();
        $duplicate_slugs = array();
        $name_map = array();
        $slug_map = array();

        foreach ( $terms as $t ) {
            if ( isset( $name_map[ $t->name ] ) ) {
                $name_map[ $t->name ][] = $t;
            } else {
                $name_map[ $t->name ] = array( $t );
            }

            if ( isset( $slug_map[ $t->slug ] ) ) {
                $slug_map[ $t->slug ][] = $t;
            } else {
                $slug_map[ $t->slug ] = array( $t );
            }
        }

        foreach ( $name_map as $group ) {
            if ( count( $group ) > 1 ) {
                $duplicate_names[] = $group;
            }
        }

        foreach ( $slug_map as $group ) {
            if ( count( $group ) > 1 ) {
                $duplicate_slugs[] = $group;
            }
        }

        return array(
            'orphan_terms'       => $orphan_terms,
            'orphan_term_tax'    => $orphan_term_tax,
            'ghost_relationships'=> $ghost,
            'incorrect_counts'   => $incorrect_counts,
            'broken_parents'     => $broken_parents,
            'unknown_taxonomies' => $unknown_taxonomies,
            'duplicate_names'    => $duplicate_names,
            'duplicate_slugs'    => $duplicate_slugs,
        );
    }

    private function render_summary_box( $audit ) {
        ?>
        <table class="widefat striped" style="max-width:800px;">
            <tbody>
                <tr><th>Orphan terms</th><td><?php echo count( $audit['orphan_terms'] ); ?></td></tr>
                <tr><th>Orphan term taxonomy rows</th><td><?php echo count( $audit['orphan_term_tax'] ); ?></td></tr>
                <tr><th>Ghost relationships</th><td><?php echo count( $audit['ghost_relationships'] ); ?></td></tr>
                <tr><th>Incorrect counts</th><td><?php echo count( $audit['incorrect_counts'] ); ?></td></tr>
                <tr><th>Broken parent chains</th><td><?php echo count( $audit['broken_parents'] ); ?></td></tr>
                <tr><th>Terms in unregistered taxonomies</th><td><?php echo count( $audit['unknown_taxonomies'] ); ?></td></tr>
                <tr><th>Duplicate names</th><td><?php echo count( $audit['duplicate_names'] ); ?></td></tr>
                <tr><th>Duplicate slugs</th><td><?php echo count( $audit['duplicate_slugs'] ); ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    private function render_orphan_terms( $audit ) {
        if ( empty( $audit['orphan_terms'] ) ) {
            echo '<p>No orphan terms detected.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Action</th></tr></thead><tbody>';

        foreach ( $audit['orphan_terms'] as $t ) {
            $delete_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=wptax_delete_orphan&term_id=' . $t->term_id ),
                'wptax_delete_orphan'
            );

            echo '<tr>';
            echo '<td>' . $t->term_id . '</td>';
            echo '<td>' . esc_html( $t->name ) . '</td>';
            echo '<td>' . esc_html( $t->slug ) . '</td>';
            echo '<td><a href="' . esc_url( $delete_url ) . '" class="button">Delete</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function handle_delete_orphan() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No.' );
        }

        check_admin_referer( 'wptax_delete_orphan' );

        $term_id = (int) $_GET['term_id'];

        wp_delete_term( $term_id, '' ); // Taxonomy doesn't matter because it has no term_tax rows.

        wp_redirect( admin_url( 'tools.php?page=' . $this->screen_slug ) );
        exit;
    }

    private function render_orphan_term_tax( $audit ) {
        if ( empty( $audit['orphan_term_tax'] ) ) {
            echo '<p>No orphaned term_taxonomy rows found.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>TT_ID</th><th>Term ID</th><th>Taxonomy</th></tr></thead><tbody>';

        foreach ( $audit['orphan_term_tax'] as $tx ) {
            echo '<tr>';
            echo '<td>' . $tx->term_taxonomy_id . '</td>';
            echo '<td>' . $tx->term_id . '</td>';
            echo '<td>' . esc_html( $tx->taxonomy ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p>This list is dangerous. These should be deleted manually in the DB after confirming no plugin uses them.</p>';
    }

    private function render_ghost_relationships( $audit ) {
        if ( empty( $audit['ghost_relationships'] ) ) {
            echo '<p>No ghost relationships found.</p>';
            return;
        }

        $delete_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wptax_delete_ghost_relationships' ),
            'wptax_delete_ghost_relationships'
        );

        echo '<p>' . count( $audit['ghost_relationships'] ) . ' ghost relationships found.</p>';
        echo '<a class="button" href="' . esc_url( $delete_url ) . '">Delete ghost relationships</a>';
    }

    public function handle_delete_ghost_relationships() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No.' );
        }

        check_admin_referer( 'wptax_delete_ghost_relationships' );

        global $wpdb;

        // Delete rows in term_relationships where term_taxonomy_id does not exist.
        $wpdb->query(
            "DELETE tr FROM $wpdb->term_relationships tr
             LEFT JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.term_taxonomy_id IS NULL"
        );

        wp_redirect( admin_url( 'tools.php?page=' . $this->screen_slug ) );
        exit;
    }

    private function render_incorrect_counts( $audit ) {
        if ( empty( $audit['incorrect_counts'] ) ) {
            echo '<p>No incorrect counts detected.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>TT_ID</th><th>Taxonomy</th><th>Stored</th><th>Real</th><th>Fix</th></tr></thead><tbody>';

        foreach ( $audit['incorrect_counts'] as $row ) {
            $tx = $row['tx'];

            $fix_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=wptax_fix_count&tt_id=' . $tx->term_taxonomy_id ),
                'wptax_fix_count'
            );

            echo '<tr>';
            echo '<td>' . $tx->term_taxonomy_id . '</td>';
            echo '<td>' . esc_html( $tx->taxonomy ) . '</td>';
            echo '<td>' . $row['stored'] . '</td>';
            echo '<td>' . $row['real'] . '</td>';
            echo '<td><a class="button" href="' . esc_url( $fix_url ) . '">Repair count</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function handle_fix_count() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No.' );
        }

        check_admin_referer( 'wptax_fix_count' );

        global $wpdb;

        $tt_id = (int) $_GET['tt_id'];

        // Recalc.
        $real_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d",
                $tt_id
            )
        );

        $wpdb->update(
            $wpdb->term_taxonomy,
            array( 'count' => $real_count ),
            array( 'term_taxonomy_id' => $tt_id ),
            array( '%d' ),
            array( '%d' )
        );

        wp_redirect( admin_url( 'tools.php?page=' . $this->screen_slug ) );
        exit;
    }

    private function render_broken_parents( $audit ) {
        if ( empty( $audit['broken_parents'] ) ) {
            echo '<p>No broken parent chains detected.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>TT_ID</th><th>Term ID</th><th>Parent ID</th><th>Taxonomy</th></tr></thead><tbody>';

        foreach ( $audit['broken_parents'] as $tx ) {
            echo '<tr>';
            echo '<td>' . $tx->term_taxonomy_id . '</td>';
            echo '<td>' . $tx->term_id . '</td>';
            echo '<td>' . $tx->parent . '</td>';
            echo '<td>' . esc_html( $tx->taxonomy ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p>Fixing these requires reassigning or flattening terms. Only you know the correct hierarchy.</p>';
    }

    private function render_unknown_taxonomies( $audit ) {
        if ( empty( $audit['unknown_taxonomies'] ) ) {
            echo '<p>No terms found in unregistered taxonomies.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>TT_ID</th><th>Term ID</th><th>Taxonomy</th></tr></thead><tbody>';

        foreach ( $audit['unknown_taxonomies'] as $tx ) {
            echo '<tr>';
            echo '<td>' . $tx->term_taxonomy_id . '</td>';
            echo '<td>' . $tx->term_id . '</td>';
            echo '<td>' . esc_html( $tx->taxonomy ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p>These usually belong to plugins or custom code no longer present. Investigate before removal.</p>';
    }

    private function render_duplicates( $audit ) {
        ?>
        <h3>Duplicate Names</h3>
        <?php
        if ( empty( $audit['duplicate_names'] ) ) {
            echo '<p>No duplicate names detected.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Term IDs</th></tr></thead><tbody>';
            foreach ( $audit['duplicate_names'] as $group ) {
                $name = $group[0]->name;
                $ids = wp_list_pluck( $group, 'term_id' );
                echo '<tr><td>' . esc_html( $name ) . '</td><td>' . implode( ', ', $ids ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        ?>
        <h3>Duplicate Slugs</h3>
        <?php
        if ( empty( $audit['duplicate_slugs'] ) ) {
            echo '<p>No duplicate slugs detected.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Slug</th><th>Term IDs</th></tr></thead><tbody>';
            foreach ( $audit['duplicate_slugs'] as $group ) {
                $slug = $group[0]->slug;
                $ids = wp_list_pluck( $group, 'term_id' );
                echo '<tr><td>' . esc_html( $slug ) . '</td><td>' . implode( ', ', $ids ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }
}

new WP_Taxonomy_Repair_Tool();
