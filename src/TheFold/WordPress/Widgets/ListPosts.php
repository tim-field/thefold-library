<?php

namespace TheFold\Wordpress\Widgets;

abstract class ListPosts extends \WP_Widget{

    protected $default_limit = 5;

    abstract function render_posts($posts, $args, $instance);

    protected function get_posts($instance, $args) {
    
        return get_posts( apply_filters( 'widget_posts_args', array(
            'posts_per_page'      => isset($instance['number']) ? $instance['number'] : $this->default_limit,
            'no_found_rows'       => true,
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true
        )));
    }
    
    public function __construct( $id_base, $name, $widget_options = array(), $control_options = array() ) {
        
        parent::__construct($id_base, $name, $widget_options = array(), $control_options = array());
        
        add_action( 'save_post', array($this, 'flush_widget_cache') );
        add_action( 'deleted_post', array($this, 'flush_widget_cache') );
        add_action( 'switch_theme', array($this, 'flush_widget_cache') );
    }

    public function widget($args, $instance) {
		$cache = array();
		if ( ! $this->is_preview() ) {
			$cache = wp_cache_get( 'thefold_widget_list_posts', 'widget' );
		}

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

		ob_start();

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';

		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

                if ($posts = $this->get_posts($instance, $args)) {
                    echo $args['before_widget']; 
                    if ( $title ) {
                        echo $args['before_title'] . $title . $args['after_title'];
                    }

                    $args['view_params']['results'] = $posts;

                    $this->render_posts($posts, $args, $instance);

                    echo $args['after_widget']; 
                };

		if ( ! $this->is_preview() ) {
			$cache[ $args['widget_id'] ] = ob_get_flush();
			wp_cache_set( 'thefold_widget_list_posts', $cache, 'widget' );
		} else {
			ob_end_flush();
		}
	}

    
    public function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = (int) $new_instance['number'];
        $this->flush_widget_cache();

        $alloptions = wp_cache_get( 'alloptions', 'options' );
        if ( isset($alloptions['widget_recent_entries']) )
            delete_option('widget_recent_entries');

        return $instance;
    }

    public function flush_widget_cache() {
        wp_cache_delete('thefold_widget_list_posts', 'widget');
    }
    
    public function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : $this->default_limit;
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

<?php
	}
}
