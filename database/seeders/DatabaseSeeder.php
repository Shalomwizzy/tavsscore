<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ── Admin account ── */
        User::firstOrCreate(
            ['email' => 'admin@tavsscore.com'],
            [
                'name'              => 'TavsScore Admin',
                'role'              => 'admin',
                'password'          => Hash::make('TavsAdmin@2024!'),
                'email_verified_at' => now(),
            ]
        );

        /* ── Sample blog posts ── */
        $posts = [
            [
                'title'    => 'Premier League 2024/25: Title Race Heats Up as Final Weeks Approach',
                'category' => 'Premier League',
                'excerpt'  => 'The Premier League title race is entering its most dramatic phase with clubs separated by just points.',
                'content'  => '<p>The Premier League title race is entering its most dramatic phase of the season, with the top clubs separated by just a handful of points. Every matchday brings high stakes and thrilling football as clubs battle for the championship trophy.</p><p>The front-runners — including Chelsea, Manchester United, Liverpool, Arsenal, and Manchester City — have shown incredible consistency throughout the campaign, but with fixture congestion and cup commitments, even the smallest dropped point could prove decisive.</p><p>Fans across the globe are glued to every result as the beautiful game delivers its finest drama. TavsScore brings you live scores, real-time updates, and AI-powered predictions to keep you ahead of the action.</p>',
            ],
            [
                'title'    => 'Champions League Quarter-Finals Preview: Europe\'s Elite Battle for Glory',
                'category' => 'Champions League',
                'excerpt'  => 'Europe\'s elite clubs clash in the Champions League quarter-finals. Full match previews and predictions.',
                'content'  => '<p>The UEFA Champions League has reached the quarter-final stage, and the remaining clubs are among the finest in world football. From the Spanish giants to the Premier League powerhouses, each side brings unique strengths to this stage.</p><h2>What to Expect</h2><p>The quality of football in the Champions League knockout rounds is consistently the highest in club football. Expect tactical battles, set-piece specialists making the difference, and goalkeepers producing season-defining saves under the floodlights of Europe\'s greatest stadiums.</p><p>Follow every kick live on TavsScore with real-time scores and AI predictions for every tie.</p>',
            ],
            [
                'title'    => 'How AI is Transforming Football Predictions in 2025',
                'category' => 'Football Analysis',
                'excerpt'  => 'Discover how AI and machine learning are changing the way we predict football match outcomes.',
                'content'  => '<p>Artificial intelligence is revolutionizing how football fans engage with the sport. From expected goals (xG) models to machine learning-powered outcome predictions, technology is giving fans deeper insights than ever before.</p><p>At TavsScore, our prediction engine analyzes recent form data, attack and defensive strength metrics to calculate win, draw, and loss probabilities for every match. The model updates automatically as new match data becomes available.</p><h2>What Makes a Good Football Prediction?</h2><p>The best predictions are built on solid statistical foundations. Key inputs include goals scored per match (attack strength), goals conceded (defensive weakness), and recent form over the last 10 matches. While no prediction is guaranteed, data-driven models consistently outperform gut-feel over large sample sizes.</p>',
            ],
            [
                'title'    => 'La Liga This Weekend: Title Contenders and El Clasico Rivalry Continue',
                'category' => 'La Liga',
                'excerpt'  => 'La Liga action continues with title contenders in crucial weekend fixtures. Live scores and predictions.',
                'content'  => '<p>La Liga continues to produce world-class football week after week. The competition for the title has been fierce throughout the season, with multiple clubs believing they have what it takes to lift the trophy in Spain\'s top division.</p><p>Spanish football is known for its technical quality, tactical sophistication, and the individual brilliance of its stars. TavsScore covers every La Liga match with live scores, in-play updates, and pre-match predictions.</p>',
            ],
            [
                'title'    => 'Understanding xG, xA and Modern Football Statistics Explained',
                'category' => 'Football Analysis',
                'excerpt'  => 'A beginner\'s guide to expected goals (xG), expected assists (xA), and modern football analytics.',
                'content'  => '<p>Modern football analysis has moved far beyond simple goals and assists. Expected Goals (xG) and Expected Assists (xA) have become standard tools for analysts, coaches, and informed fans.</p><h2>What is Expected Goals (xG)?</h2><p>Expected Goals measures the quality of a shot based on factors such as distance from goal, angle, body part used, and the assist type. A shot from 6 yards straight in front of goal might have an xG of 0.85, while a long-range effort might have just 0.04.</p><h2>Why xG Matters</h2><p>Teams that consistently outperform their xG may be due for regression. TavsScore\'s prediction model incorporates team-level scoring rates to build probability models for every match. Check our Predictions page for today\'s tips.</p>',
            ],
        ];

        foreach ($posts as $data) {
            BlogPost::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                array_merge($data, [
                    'slug'         => Str::slug($data['title']),
                    'author'       => 'TavsScore Editorial',
                    'is_published' => true,
                    'published_at' => now()->subDays(rand(1, 14)),
                ])
            );
        }
    }
}
