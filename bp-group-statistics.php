<?php
/*
Plugin Name: BP Group Statistics
Version: 0.1-alpha
Description: This plugin will return pretty visualizations for group membership data. 
Author: Jonathan Reeve 
Author URI: YOUR SITE HERE
Plugin URI: PLUGIN SITE HERE
Text Domain: bp-group-statistics
Domain Path: /languages
*/

function bp_group_statistics_admin_menu() {
	$plugin_page = add_menu_page( __('Group Statistics','bp-group-statistics'), __('Group Statistics','bp-group-statistics'), 'manage_options', 'bp-group-statistics', 'bp_group_statistics_admin_display' );
	add_action('admin_print_scripts-' . $plugin_page, 'bp_group_statistics_enqueue_scripts');
}
add_action( 'admin_menu', 'bp_group_statistics_admin_menu', 70 );
add_action( 'admin_menu', 'my_plugin_admin_menu' );

function bp_group_statistics_admin_init() {
	/* Register our script. */
	wp_register_script( 'bp-group-stats-d3', plugins_url( '/d3.js', __FILE__ ) );
} 
add_action( 'admin_init', 'bp_group_statistics_admin_init' );

function bp_group_statistics_enqueue_scripts() { 
	/* Link our already registered script to a page */
	wp_enqueue_script( 'bp-group-stats-d3' ); 
	wp_register_style( 'bp-group-statistics', plugins_url( 'bp-group-statistics/assets/css/style.css' ) ); 
	wp_enqueue_style( 'bp-group-statistics' ); 
} 
add_action( 'wp_enqueue_scripts', 'bp_group_statistics_enqueue_scripts' ); 

function bp_group_statistics_admin_display() { 
	// admin page
?>
<h1>Helllooooooo!</h1> 
<?php 
//$groups_array = groups_get_groups( array( 'per_page' => 10000 ) ); 
//$groups = $groups_array['groups']; 
//$members_array = bp_core_get_users( array( 'per_page' => 10000 ) );
//$members = $members_array['members']; 

	global $wpdb; 
	$results = $wpdb->get_results( 'select t1.group_id as source, t2.group_id as target, count(t1.user_id) as strength from wp_bp_groups_members as t1, wp_bp_groups_members as t2 where t1.group_id < t2.group_id and t1.user_id = t2.user_id group by source, target order by strength desc limit 400' ); 

	//_log( 'here come some groups!' ); 
	////_log( $results ); 
	//_log( $results ); 
	$linked_groups = array(); 
	foreach( $results as $result ) { 
		if ( $result->strength > 2 ) { 
			$linked_groups[] = $result->source; 
			$linked_groups[] = $result->target; 
		} 
	} 
	//_log( 'here are the linked groups raw:' ); 
	//_log( $linked_groups ); 
	$linked_groups_unique = array_unique($linked_groups); 
	// now reindex it
	$linked_groups_unique = array_values($linked_groups_unique); 
	//_log( 'here are the linked groups uniquej:' ); 
	//_log( $linked_groups_unique ); 
	$nodes = array(); 
	foreach( $linked_groups_unique as $linked_group ) { 
		$group = groups_get_group( array( 'group_id' => $linked_group ) ); 
		//_log('here comes the group!'); 
		//_log($group); 
		$nodes[] = array( 
			'name' => $linked_group, 
			'label' => $group->name 
		); 
	} 
	foreach( $results as $result ) { 
		$result->source = array_search( $result->source, $linked_groups_unique ); 
		$result->target = array_search( $result->target, $linked_groups_unique ); 
	} 
	echo '{ "links": ' . json_encode($results) . ', "nodes": ' . json_encode($nodes) . '}'; 

?> 
	<pre> <?php 
//print_r( $groups ); 
//print_r( $members_array ); 
?> </pre> 
<div id="d3container"></div> 
    <script>
		var mydata = <?php echo '{ "links": ' . json_encode($results) . ', "nodes": ' . json_encode($nodes) . '}'; ?>  

		// test data. 
		var testdata = <?php echo '{
    "links": [
        {
		"source": 0, 
		"target": 1
        }, 
	{ 
		"source": 0, 
		"target": 2
	} 
    ], 
    "nodes": [
        {
            "name": "Group A"
        }, 
        {
            "name": "Group B"
        }, 
        {
            "name": "Group C"
        } 
    ]
}'; ?> 
	    console.log('here comes the data!'); 
	    console.dir(mydata); 
            
            var width = 600,
                height = 600;
            
            var svg = d3.select('#d3container')
              .append('svg')
                .attr('width', width)
                .attr('height', height);

	    var gnodes = svg.selectAll('g.gnode')
		    .data(mydata.nodes)
		    .enter()
		    .append('g')
		    .classed('gnode', true); 

	    var node = gnodes.append("circle")
		    .attr("class", "node")
		    .attr('r', 5)
		    .style('fill', 'red'); 

            // draw the graph nodes
	    /*
             *var node = svg.selectAll("circle.node")
             *  .data(mydata.nodes)
             *  .enter()
             *  .append("circle")
             *    .attr("class", "node")
	     *    .style("fill", "red")
             *    .attr("r", 12); 
	     */

	    var labels = gnodes.append("text")
		.text(function(d) { return d.label });
            
            // draw the graph edges
            var link = svg.selectAll("line.link")
              .data(mydata.links)
              .enter().append("line")
              .style('stroke','black')
	      .style("stroke-width", function(d) { return ( d.strength / 75 ); }); 
            
            // create the layout
            var force = d3.layout.force()
                .charge(-220)
                .linkDistance(90)
                .size([width, height])
                .nodes(mydata.nodes)
                .links(mydata.links)
                .start();
            
            // define what to do one each tick of the animation
            force.on("tick", function() {
                link.attr("x1", function(d) { return d.source.x; })
                    .attr("y1", function(d) { return d.source.y; })
                    .attr("x2", function(d) { return d.target.x; })
                    .attr("y2", function(d) { return d.target.y; });
                
                //node.attr("cx", function(d) { return d.x; })
                    //.attr("cy", function(d) { return d.y; });
		gnodes.attr("transform", function(d) { return 'translate(' + [d.x, d.y] + ")"; }); 
                });

            // bind the drag interaction to the nodes
            gnodes.call(force.drag);
    </script>
<?php 
} 
