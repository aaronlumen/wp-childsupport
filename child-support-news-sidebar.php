<?php
/*
Plugin Name: Child Support News Sidebar
Plugin URI: https://justice.surina.xyz
Description: Displays a sidebar widget with the latest news articles related to child support reimbursement, Title IV-D, and high-dollar awards.
Version: 1.1
Author: Aaron Surina
Directions:   Place this file in WebsiteDirectory/wp-content/plugins and name it child-suppport-news-sidebar.php.   Go to plugins, activate it and add your news apiKey under settings in wordpress.
There will be a new item under settings in wordpress called Child Support News.  Add the apiKey into that section.
That's it.  Enjoy
*/

// Admin menu for API key input
function csn_admin_menu() {
    add_options_page('Child Support News Settings', 'Child Support News', 'manage_options', 'csn-settings', 'csn_settings_page');
}
add_action('admin_menu', 'csn_admin_menu');

function csn_settings_page() {
    ?>
    <div class="wrap">
        <h1>Child Support News Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('csn_settings_group');
            do_settings_sections('csn-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function csn_register_settings() {
    register_setting('csn_settings_group', 'csn_newsapi_key');

    add_settings_section('csn_main_section', 'API Configuration', null, 'csn-settings');

    add_settings_field(
        'csn_newsapi_key_field',
        'NewsAPI Key',
        function() {
            $value = esc_attr(get_option('csn_newsapi_key'));
            echo "<input type='text' name='csn_newsapi_key' value='{$value}' class='regular-text' />";
        },
        'csn-settings',
        'csn_main_section'
    );
}
add_action('admin_init', 'csn_register_settings');

// Fetch and cache child support-related news articles
function csn_fetch_child_support_news() {
    $api_key = get_option('csn_newsapi_key');
    $api_url = 'https://newsapi.org/v2/everything?q=%22child%20support%22%20AND%20(reimbursement%20OR%20%22Title%20IV-D%22%20OR%20%22judge%20awarded%22%20OR%20unconstitutional%20OR%20overpayment%20OR%20garnishment)&language=en&pageSize=10&sortBy=publishedAt&apiKey=' . $api_key;

    $transient_key = 'csn_child_support_news';
    $news_data = get_transient($transient_key);

    if (!$news_data) {
        $response = wp_remote_get($api_url);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $news_data = json_decode($body, true);
        }

        // Fallback to Google News RSS if NewsAPI fails or returns empty
        if (empty($news_data['articles'])) {
            $rss = simplexml_load_file('https://news.google.com/rss/search?q=child+support+reimbursement+Title+IV-D+judge+awarded&hl=en-US&gl=US&ceid=US:en');
            $news_data['articles'] = [];

            foreach ($rss->channel->item as $item) {
                $news_data['articles'][] = [
                    'title' => (string) $item->title,
                    'url' => (string) $item->link,
                    'source' => ['name' => 'Google News'],
                    'publishedAt' => date('c', strtotime($item->pubDate)),
                    'urlToImage' => '',
                ];
            }
        }

        if (!empty($news_data['articles'])) {
            set_transient($transient_key, $news_data, HOUR_IN_SECONDS);
        }
    }

    if (empty($news_data['articles'])) {
        return '<p>No related news articles found.</p>';
    }

    $output = '<div class="child-support-news-sidebar"><ul>';
    foreach ($news_data['articles'] as $article) {
        if (stripos($article['title'], 'child support') === false) continue;
        $title = esc_html($article['title']);
        $url = esc_url($article['url']);
        $source = esc_html($article['source']['name']);
        $date = date('M j, Y', strtotime($article['publishedAt']));
        $image = isset($article['urlToImage']) && !empty($article['urlToImage']) ? esc_url($article['urlToImage']) : '';

        $output .= '<li>';
        if ($image) {
            $output .= "<img src='{$image}' alt='{$title}' style='max-width:100%;height:auto;margin-bottom:5px;' />";
        }
        $output .= "<a href='{$url}' target='_blank'>{$title}</a><br><small>{$source} • {$date}</small></li>";
    }
    $output .= '</ul></div>';

    return $output;
}

// Shortcode for use in widgets or posts
add_shortcode('child_support_news', 'csn_fetch_child_support_news');

// Register widget to sidebar
function csn_register_sidebar_widget() {
    register_sidebar(array(
        'name' => 'Child Support News',
        'id' => 'child_support_news_sidebar',
        'before_widget' => '<div class="widget child-support-news">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('widgets_init', 'csn_register_sidebar_widget');

// Auto-inject into registered sidebar (optional, or use shortcode/widget block manually)
function csn_display_news_widget() {
    if (is_active_sidebar('child_support_news_sidebar')) {
        dynamic_sidebar('child_support_news_sidebar');
    }
}
add_action('wp_footer', 'csn_display_news_widget');

// Optional CSS
function csn_custom_styles() {
    echo '<style>
        .child-support-news-sidebar ul { list-style: none; padding-left: 0; }
        .child-support-news-sidebar li { margin-bottom: 20px; }
        .child-support-news-sidebar img { display: block; margin-bottom: 5px; border-radius: 4px; }
        .child-support-news-sidebar small { color: #666; font-size: 0.85em; }
    </style>';
}
add_action('wp_head', 'csn_custom_styles');
