<?php

class PsGenerator extends Base implements GeneratorInterface
{
    const TIME_FORMAT = '%Y/%m/%d %H:%M:%S';

    private static $singleton = null;

    private $hand;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (is_null(self::$singleton))
            self::$singleton = new PsGenerator();

        return self::$singleton;
    }

    private function setHand(Hand $hand)
    {
        $this->hand = $hand;
    }

    private function getHand()
    {
        return $this->hand;
    }

    private function getId()
    {
        return $this->getHand()->getId();
    }
    
    private function getGame()
    {
        $tr_game = array(
            Game::HOLDEM => "Hold'em",
        );

        return $tr_game[$this->getHand()->getGame()];
    }

    private function getLimit()
    {
        $tr_limit = array(
            Limit::NOLIMIT => "No Limit",
        );

        return $tr_limit[$this->getHand()->getLimit()];
    }

    private function getGameSb()
    {
        return $this->getHand()->getGameSb();
    }

    private function getGameBb()
    {
        return $this->getHand()->getGameBb();
    }

    private function getAestTime()
    {
        return strftime($this::TIME_FORMAT, $this->getHand()->getTimestamp()) . " AEST";
    }

    private function getEtTime()
    {
        $time_diff = - (15 * 60 * 60);

        return strftime($this::TIME_FORMAT, $this->getHand()->getTimestamp() + $time_diff) . " ET";
    }

    private function getHandInitText()
    {
        $stakes_text = sprintf("%s/%s", $this->formatChips($this->getGameSb()),
                $this->formatChips($this->getGameBb()));
        if (! $this->getHand()->getIsPlayMoney())
            $stakes_text .= ' ' . $this->getCurrencyCode();
        $stakes_text = '(' . $stakes_text . ')';

        return sprintf("PokerStars Hand #%d:  %s %s %s - %s [%s]\n",
            $this->getId(), $this->getGame(), $this->getLimit(), $stakes_text,
            $this->getAestTime(), $this->getEtTime());
    }

    private function getTableId()
    {
        return $this->getHand()->getTableId();
    }

    private function getTableSize()
    {
        $tr_table_size = array(
            TableSize::TWO => '2-max',
            TableSize::SIX => '6-max',
            TableSize::NINE => '9-max'
        );

        return $tr_table_size[$this->getHand()->getTableSize()];
    }

    private function getDealerSeat()
    {
        $dealer_seat = $this->getHand()->getDealerSeat();
        if ($dealer_seat)
            return $dealer_seat;

        $i = 1;
        foreach ($this->getHand()->getSeats() as $player) {
            if ($player->getSeat() != $i) // Found a gap
                return $i;

            $i++;
        }

        return $i; // One after last
    }

    private function getTableText()
    {
        return sprintf("Table '%s' %s Seat #%d is the button\n",
            $this->getTableId(), $this->getTableSize(), $this->getDealerSeat());
    }

    private function getCurrencySymbol()
    {
        return $this->getHand()->getCurrency()->getSymbol();
    }

    private function getCurrencyCode()
    {
        return $this->getHand()->getCurrency()->getCode();
    }

    private function formatChips($chips)
    {
        $out = '';
        $format = $this->getHand()->getFormat();
        Format::validate($format);

        if ($this->getHand()->getFormat() == Format::CASH_GAME) {
            if (! $this->getHand()->getIsPlayMoney())
                $out .= $this->getCurrencySymbol();
            
            $format_string = '%.02f';
        } else {
            $format_string = '%d';
        }

        $out .= sprintf($format_string, $chips);

        return $out;
    }

    private function getSeatsText()
    {
        $out = '';

        foreach ($this->getHand()->getSeats() as $player) {
            $out .= sprintf("Seat %d: %s (%s in chips)\n",
                $player->getSeat(), $player->getName(), $this->formatChips($player->getChips()));
        }

        return $out;
    }

    private function getPostsText()
    {
        $out = '';

        $tr_post = array(
            Post::SB => 'small blind',
            Post::BB => 'big blind',
        );

        foreach ($this->getHand()->getPosts() as $posts_of_type) {
            if (! is_array($posts_of_type))
                $posts_of_type = array($posts_of_type);

            foreach ($posts_of_type as $post) {
                $out .= sprintf("%s: posts %s %s\n", $post->getName(), $tr_post[$post->getType()],
                    $this->formatChips($post->getChips()));
            }
        }

        return $out;
    }

    private function formatCards($cards)
    {
        return '[' . implode(' ', $cards) . ']';
    }

    protected function getHoleCardsText()
    {
        $out = '';

        $out .= "*** HOLE CARDS ***\n";

        $player = $this->getHand()->getHeroPlayer();
        $out .= sprintf("Dealt to %s %s\n", $player->getName(),
            $this->formatCards($player->getCards()));

        $out .= $this->getActionsText(Street::PREFLOP);

        return $out;
    }

    protected function getActionsText($street)
    {
        $out = '';

        $tr_type = array(
            Action::FOLD => 'folds',
            Action::CHECK => 'checks',
            Action::CALL => 'calls',
            Action::BET => 'bets',
            Action::RAISE => 'raises',
        );

        foreach ($this->getHand()->getAction($street) as $action) {
            if ($action->getType() == Action::RETRN) {
                $out .= sprintf("Uncalled bet (%s) returned to %s\n",
                    $this->formatChips($action->getChips()), $action->getName());

            } elseif (in_array($action->getType(), Action::getTypesWithoutChips())) {
                $out .= sprintf("%s: %s\n", $action->getName(), $tr_type[$action->getType()]);

            } elseif ($action->getType() == Action::RESULT) {
                $out .= sprintf("%s: collects %s", $action->getName(),
                    $this->formatChips($action->getChips()));

                // from the pot/side-pot

                $out .= "\n";

            } elseif (in_array($action->getType(), Action::getTypesWithChips())) {
                $out .= sprintf("%s: %s %s", $action->getName(), $tr_type[$action->getType()],
                    $this->formatChips($action->getChips()));

                if (in_array($action->getType(), Action::getTypesWithToChips()))
                    $out .= sprintf(" to %s", $this->formatChips($action->getToChips()));

                if (in_array($action->getType(), Action::getTypesWithAllIn())
                        && $action->getIsAllIn()) {
                    $out .= sprintf(" and is all-in", $this->formatChips($action->getToChips()));
                }

                $out .= "\n";
            }
        }

        return $out;
    }

    private function getFlopText()
    {
        $out = '';
        $flop_cards = $this->getHand()->getCommunityCards(Street::FLOP);

        if (empty($flop_cards))
            return $out;

        $out .= sprintf("*** FLOP *** %s\n", $this->formatCards($flop_cards));

        $out .= $this->getActionsText(Street::FLOP);

        return $out;
    }

    private function getTurnText()
    {
        $out = '';
        $flop_cards = $this->getHand()->getCommunityCards(Street::FLOP);
        $turn_cards = $this->getHand()->getCommunityCards(Street::TURN);

        if (empty($turn_cards))
            return $out;

        $out .= sprintf("*** TURN *** %s %s\n", $this->formatCards($flop_cards),
            $this->formatCards($turn_cards));

        $out .= $this->getActionsText(Street::TURN);

        return $out;
    }

    private function getRiverText()
    {
        $out = '';
        $turn_board = $this->getHand()->getBoard(Street::TURN);
        $river_cards = $this->getHand()->getCommunityCards(Street::RIVER);

        if (empty($river_cards))
            return $out;

        $out .= sprintf("*** RIVER *** %s %s\n", $this->formatCards($turn_board),
            $this->formatCards($river_cards));

        $out .= $this->getActionsText(Street::RIVER);

        return $out;
    }

    private function getRanking($cards)
    {
        return Ranking::calculate($cards, $this->getHand()->getBoard());
    }

    private function formatRanking($ranking)
    {
        $ch = function ($card) {
            return Ranking::cardRankStr($card);
        };

        $hi = $ch($ranking->getHighCard());
        if ($ranking->getLowCard() !== null)
            $lo = $ch($ranking->getLowCard());

        $out = '';

        $out .= Ranking::handRankStr($ranking->getRank());

        switch ($ranking->getRank()) {

        case Ranking::FULL_HOUSE:
            $out .= sprintf(', %ss full of %ss', $hi, $lo);
            break;

        case Ranking::TWO_PAIR:
            $out .= sprintf(', %ss and %ss', $hi, $lo);
            break;

        case Ranking::QUADS:
        case Ranking::TRIPS:
            $out .= sprintf(', %ss', $hi);
            break;

        case Ranking::PAIR:
            $out .= sprintf(' of %ss', $hi);
            break;

        default:
            $out .= sprintf(' %s', $hi);
            break;
        }

        $kickers = array();
        foreach ($ranking->getKickers() as $k)
            $kickers[$k] = $ch($k);
        if (count($kickers) > 0) {
            $out .= sprintf(', %s %s', (count($kickers) == 1 ? 'kicker' : 'kickers'),
                implode(', ', $kickers));
        }

        return $out;
    }

    private function getShowdownText()
    {
        $out = '';

        $showdown_action = $this->getHand()->getShowdownAction();

        if (empty($showdown_action))
            return $out;

        $out .= "*** SHOW DOWN ***\n";

        foreach ($showdown_action as $action) {
            if ($action->getType() == Action::SHOWDOWN) {
                $cards = $this->getHand()->getPlayer($action->getName())->getCards();
                $ranking = $this->getRanking($cards);

                $out .= sprintf("%s: shows %s (%s)\n", $action->getName(),
                    $this->formatCards($cards), $this->formatRanking($ranking));

            } elseif ($action->getType() == Action::MUCK) {
                $out .= sprintf("%s: mucks hand\n", $action->getName());

            } elseif ($action->getType() == Action::RESULT) {
                $out .= sprintf("%s collected %s from pot\n", $action->getName(),
                    $this->formatChips($action->getChips()));
            }
        }

        return $out;
    }

    private function getSummarySeatsText()
    {
        $out = '';

        foreach ($this->getHand()->getSummary() as $seat) {
            $str_position = '';
            if (isset($tr_summary_position[$action['player']]))
                $str_position = ' (' . $tr_summary_position[$action['player']] . ')';

            if (empty($action['action'])) {
                $seat = $this->getPlayerSeat($action['player']);

                $won_with = '';
                if (! empty($action['ranking']))
                    $won_with = sprintf(' with %s', $action['ranking']);

                if (! $won_with) // make up something
                    $won_with = ' with a pair of Deuces';

                $result = sprintf('showed [%s %s] and won ($%.02f)%s', $seat['card1'], $seat['card2'], $action['chips'], $won_with);
            } elseif ($action['action'] == 'Folded') {
                $result = $this->tr_summary_action[$action['action_full']];
            } elseif ($action['action'] == 'Mucked') {
                $result = sprintf('mucked [%s %s]', $action['card1'], $action['card2']);
            }

            $out .= sprintf("Seat %d: %s%s %s\n", $action['seatno'], $this->me($action['player']), $str_position, $result);
        }

        return $out;
    }

    /**
     * Generate Poker Stars hand summary section
     */
    protected function getSummaryText()
    {
        $out = '';
        $all_action = $this->getAllAction();

        $out .= "*** SUMMARY ***\n";

        $result_pot = 0;
        $total_pot = $this->summary['pot']['chips'];
        foreach ($all_action as $action) {
            if (empty($action))
                continue;

            if (strpos($action['action'], 'Hand result') === 0) {
                $result_pot += $action['chips'];
            } elseif ($action['action'] == 'Return') {
                $result_pot -= $action['chips'];
                $total_pot -= $action['chips'];
            }
        }
        $rake = $total_pot - $result_pot;
        $out .= sprintf("Total pot $%.02f | Rake $%.02f\n", $this->summary['pot']['chips'], $rake);

        if (! empty($this->summary['board']))
            $out .= sprintf("Board [%s]\n", implode(' ', array_slice($this->summary['board'], 1, 5)));

        $out .= $this->getSummarySeatsText();

        return $out;
    }

    /**
     * Use collected Ignition Casino hand data to generate Poker Stars hand text
     */
    public function getText(Hand $hand)
    {
        $this->setHand($hand);

        $out = '';

        $tr_summary_position = array(
            'Dealer' => 'button',
            'Big Blind' => 'big blind',
            'Small Blind' => 'small blind',
        );

        $out .= $this->getHandInitText();

        $out .= $this->getTableText();

        $out .= $this->getSeatsText();

        $out .= $this->getPostsText();

        $out .= $this->getHoleCardsText();

        $out .= $this->getFlopText();

        $out .= $this->getTurnText();

        $out .= $this->getRiverText();

        $out .= $this->getShowdownText();

        //$out .= $this->getSummaryText();

        $out .= "\n";

        return $out;
    }
}


