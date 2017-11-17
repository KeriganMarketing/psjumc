<?php

use Carbon\Carbon;
use KeriganSolutions\FacebookFeed\FacebookEvents;

/**
 * @package KMA
 * @subpackage kmaslim
 * @since 1.0
 * @version 1.2
 */
$headline = ($post->page_information_headline != '' ? $post->page_information_headline : $post->post_title);
$subhead  = ($post->page_information_subhead != '' ? $post->page_information_subhead : '');

$feed          = new FacebookEvents();
$results       = $feed->fetch(9);
$now           = Carbon::now('America/Chicago');
$futureCounter = 0;
$pastCounter   = 0;

include(locate_template('template-parts/sections/top.php'));
?>
<div id="mid" >
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <div class="section top-section support-header" >
        </div>
        <div id="content" class="section support events-archive">
            <div class="container">

                <div class="entry-content">
                    <h1 class="title"><?php echo $headline; ?></h1>
                    <?php echo ($subhead!='' ? '<p class="subtitle">'.$subhead.'</p>' : null); ?>
                    <?php the_content(); ?>

                    <h2 class="title">Upcoming Events</h2>
                    <div class="columns is-multiline">
                        <?php
                        foreach ($results->data as $event) {
                            if (Carbon::parse($event->start_time)->gt($now)) { ?>
                            <div class="column is-6">
                                <?php
                                    include(locate_template('template-parts/partials/mini-event.php'));
                                    $futureCounter++;
                                ?>
                            </div>
                            <?php
                            }
                        }
                        if ($futureCounter == 0) {
                            echo '<div class="column is-6"><p>No events are currently scheduled</p></div><hr>';
                        }
                        ?>
                    </div>
                    <h2 class="title">Previous Events</h2>
                    <div class="columns is-multiline">
                        <?php
                        foreach ($results->data as $event) {
                            if (Carbon::parse($event->start_time)->lt($now) && Carbon::parse($event->start_time)->gt($now->copy()->subMonths(6))) { ?>
                            <div class="column is-6">
                                <?php include(locate_template('template-parts/partials/mini-event.php'));
                                $pastCounter++;
                                ?>
                            </div>
                        <?php
                            }
                            if ($pastCounter >= 4) {
                                break;
                            }
                        }
                        if ($pastCounter == 0) {
                            echo '<p>No recent events to display</p><hr>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </article>
    <div class="section connect">
        <div class="container">
            <?php include(locate_template('template-parts/sections/connect.php')); ?>
        </div>
    </div>
</div>
<?php include(locate_template('template-parts/sections/bot.php')); ?>
