<?php

class Main extends TelegramApp\Module {

	public function start(){
		if(!$this->telegram->is_chat_group()){
			$this->help();
		}
	}

	public function help(){
		$str = "<b>¡Bienvenido a Memondo!</b>\n\n"
				."Si estás aburrido y quieres pasar un buen rato, ¡este es tu sitio! Pide los chistes o GIFs que quieras.\n\n"
                ."Bot creado por @duhow, gracias a memondo.com por haber creado esta comunidad de usuarios y contenido!";

		$this->telegram->send
			->text($str, "HTML")
		->send();

		$this->end();
	}

    /*
	adv - Asco de Vida.
	tqd - Tenía que decirlo.
	aor - ¿Ahorrador o Rata?
	cr - Cuánta razón.
	vef - Visto en las Redes.
	vg - Vaya GIF.
	*/

    private function process($url, $feed, $categories, $action = NULL, $special = NULL){
        $action = strtolower($action);
        if($action == "nuevo"){
            $data = file_get_contents($url);
            $xml = simplexml_load_string($data);

            $msgs = array();
            foreach($xml->channel->item as $i){
                $item['id'] = filter_var(strval($i->guid), FILTER_SANITIZE_NUMBER_INT);
                $item['url'] = strval($i->guid);
                $item['date'] = strtotime(strval($i->pubDate));

                if($special == "image"){
                    $item['image'] = strval($i->description);
                    $pos = strpos($item['image'], "<img src") + strlen("<img src='");
                    $last = strpos($item['image'], '" />');
                    $item['image'] = substr($item['image'], $pos, $last - $pos);
                    $item['text'] = strval($i->title);
                    $item['category'] = "";
                }elseif($special == "video"){
                    $item['image'] = strval($i->description);
                    $pos = strpos($item['image'], "<video");
                    $pos = strpos($item['image'], "<source", $pos) + strlen("<source");
                    $pos = strpos($item['image'], "<source", $pos); // HACK twice for MP4.
                    $pos = strpos($item['image'], 'src=', $pos) + strlen("src='");
                    $last = strpos($item['image'], '"', $pos);
                    $item['image'] = substr($item['image'], $pos, $last - $pos);
                    $item['text'] = strval($i->title);
                    $item['category'] = "";
                }else{
                    $item['text'] = strval($i->description);
                    $item['text'] = substr($item['text'], 0, strpos($item['text'], '<br/'));
                    $item['category'] = strval($i->guid);
                    $item['category'] = substr($item['category'], strpos($item['category'], '.com/') + strlen('.com/'));
                    $item['category'] = substr($item['category'], 0, strpos($item['category'], '/'));
                }
                $msgs[] = $item;
            }

            $sel = 0;

            return $this->print_msg($msgs[$sel]);
        }elseif(empty($action) or in_array($action, ["aleatorio", "rand", "random"])){
            $cat = $categories[mt_rand(0, count($categories) - 1)];
			$page = mt_rand(1, 6);

			$msgs = $this->categoria($cmd, $cat, $page);
			$sel = mt_rand(0, count($msgs) - 1);

			return $this->print_msg($msgs[$sel]);
        }elseif(in_array($action, $categories)){
            $page = mt_rand(1, 6);

            $msgs = $this->categoria($cmd, $action, $page);
            $sel = mt_rand(0, count($msgs) - 1);

            return $this->print_msg($msgs[$sel]);
        }else{
            $str = $this->telegram->emoji(":warning:") ." Las categorías disponibles son: " ."\n";
            $str .= implode(", ", $categories) .".";
            $this->telegram->send
                ->text($str)
            ->send();
            $this->end();
        }
    }

    // TODO FIXME
    private function categoria($code, $url, $category, $index = 1){
		$url = $memondo[$site]['url'] ."$category/p/$index";
		if(in_array($code, ["cr", "cc", "vg"])){ $url = $memondo[$site]['url'] ."aleatorio"; }
		$web = file_get_contents($url);

		$pos = 1;
		$msgs = array();
		$mpos = $pos;
		while($pos !== FALSE){
			$find = '<div class="box story"';
			if($site == "aor"){ $find = '<div class="story clearfix">'; } // Ahorrador o rata

			$pos = strpos($web, $find, $pos);
			if($pos !== FALSE){
				$str = "";
				if(count($msgs) > 20){ $pos = FALSE; }
				$pos = strpos($web, 'Publicado', $pos);
				$last = strpos($web, '</a>', $pos);
				$item['dateuser'] = trim(strip_tags(substr($web, $pos, $last - $pos)));
				/* if($mpos > $pos){ die(); }
				$mpos = $pos; */

				if(strpos($item['dateuser'], '&#9792;') !== FALSE){ $item['gender'] = 'male'; }
				elseif(strpos($item['dateuser'], '&#9794;') !== FALSE){ $item['gender'] = 'female'; }
				else{ $item['gender'] = 'unknown'; }

				$item['dateuser'] = trim(str_replace(["Publicado por", "Publicado", "&#9792;", "&#9794;", " / $category"], "", $item['dateuser']));


				if(($tpos = strpos($item['dateuser'], " el ")) !== FALSE){
					$item['date'] = substr($item['dateuser'], $tpos + strlen(" el "));
					$item['date'] = strtotime($item['date']);
					$item['dateuser'] = substr($item['dateuser'], $pos);
				}

				$item['user'] = $item['dateuser'];
				unset($item['dateuser']);

				if(in_array($site, ["cr", "cc", "vef", "vg"])){
					$pos = strpos($web, '<h2>', $pos);
					$last = strpos($web, '</h2>', $pos);
					$item['text'] = trim(strip_tags(substr($web, $pos, $last - $pos)));
				}else{
					$find = '<p class="story_content">';
					if($site == "aor"){ $find = '<div class="caption">'; }
					elseif($site == "vef"){ $find = '<div class="story_content"'; }
					$pos = strpos($web, $find, $pos);
					$last = strpos($web, '</p>', $pos);
					$item['text'] = trim(strip_tags(substr($web, $pos, $last - $pos)));
				}
				/* if($mpos > $pos){ die(); }
				$mpos = $pos; */

				if(isset($memondo[$site]['special']) && $memondo[$site]['special'] == "image"){
					$pos = strpos($web, '<img src', $pos) + strlen('<img src="');
					$last = strpos($web, '" ', $pos);
					$item['image'] = substr($web, $pos, $last - $pos - 1);
					if(strpos($item['image'], '?') !== FALSE){
						$item['image'] = substr($item['image'], 0, strpos($item['image'], '?'));
					}
				}elseif(isset($memondo[$site]['special']) && $memondo[$site]['special'] == "video"){
					$pos = strpos($web, '<video', $pos);
					// $last = strpos($web, '</video>', $pos) + strlen("</video>");
					$pos = strpos($web, '<source', $pos) + strlen("<source");
					$pos = strpos($web, '<source', $pos); // HACK twice for MP4
					$pos = strpos($web, 'src="', $pos) + strlen("src='");
					$last = strpos($web, '"', $pos);
					$item['image'] = substr($web, $pos, $last - $pos);

				}
				/* if($mpos > $pos){ die(); }
				$mpos = $pos; */

				if(in_array($site, ["cr", "cc", "vg"])){
					$pos = strpos($web, '<a href', $pos - 150) + strlen('<a href="'); // BACK
					$last = strpos($web, '" ', $pos) + 1;
					$item['category'] = NULL;
					$item['url'] = substr($web, $pos, $last - $pos);
					/* if(strpos($item['url'], '?') !== FALSE){
						$item['url'] = substr($item['url'], 0, strpos($item['url'], '?'));
					} */
					$item['id'] = str_replace($memondo[$site]['url'], "", $item['url']);
					$item['id'] = substr($item['id'], 0, strpos($item['id'], '/'));
					$item['url'] = $memondo[$site]['url'] .$item['id'] .'/';
				}else{
					$pos = strpos($web, $memondo[$site]['url'] ."$category/", $pos) + strlen($memondo[$site]['url'] ."$category/");
					$last = strpos($web, " class", $pos);
					$item['id'] = filter_var(substr($web, $pos, $last - $pos), FILTER_SANITIZE_NUMBER_INT);
					$item['category'] = $category;
					$item['url'] = $memondo[$site]['url'] .$item['category'] ."/" .$item['id'];
				}
				/* if($mpos > $pos){ die(); }
				$mpos = $pos; */

				$msgs[] = $item;

			}
		}
		// $this->telegram->send->text(json_encode($msgs))->send();

		return $msgs;
	}

    private function print_msg($msg, $edit = FALSE){
        if(isset($msg['image'])){
			$str = '<a href="' .$msg['image'] .'">' .$msg['text'] .'</a>';
		}else{
			$sub = ['ADV', 'TQD', '¿Ahorrador o rata?'];
			$sub_bold = array();
			foreach($sub as $t){ $sub_bold[] = '<b>' .$t .'</b>'; }
			$msg['text'] = str_replace($sub, $sub_bold, $msg['text']);
			$msg['text'] = str_replace('&quot;', '"', $msg['text']);

			$str = '<i>' .ucwords(strtolower($msg['category'])) .'</i>';
			$str .= " - " .$this->parsedate($msg['date']);
			$str .= "\n";
			$str .= $msg['text'];
		}

		$this->telegram->send
			->inline_keyboard()
				->row()
					->button("Abrir", $msg['url'])
					// ->button("Compartir", strip_tags($msg['text']), FALSE)
				->end_row()
			->show()
			->notification(FALSE)
			->text($str, 'HTML')
		->send();
	}

    private function parsedate($date, $format = 1){
		$now = time();
		if(!is_numeric($date)){ $date = strtotime($date); }

		$diff = abs($date - $now);

		$days = floor($diff / (3600*24));
		$diff = $diff - ($days * (3600*24));

		$hours = floor($diff / 3600);
		$diff = $diff - ($hours * 3600);

		$minutes = floor($diff / 60);
		$diff = $diff - ($minutes * 60);

		$str = "";
		if($format == 1){
			$str = ($date > $now ? "en " : "hace ");
			$str .= ($days ? $days ."d " : "");
			$str .= ($hours ? $hours ."h " : "");
			$str .= ($minutes ? $minutes ."m " : "");
		}

		return $str;
	}



    // Asco de Vida
    public function adv($action = NULL){
        $url = 'http://www.ascodevida.com/';
        $feed = 'http://feeds2.feedburner.com/ascodevida';
        $categories = ["amistad", "ave", "estudios", "picante", "trabajo", "amor", "dinero", "familia", "salud", "varios"];

        return $this->process($url, $feed, $categories, $action);
    }

    // Tenia Que Decirlo
    public function tqd($action = NULL){
        $url = 'http://www.teniaquedecirlo.com/';
        $feed = 'http://feeds2.feedburner.com/teniaquedecirlo';
        $categories = ["amistad", "canis", "dinero", "familia", "higiene", "picante", "reflexiones", "television", "varios",
                    "amor", "comportamiento", "estudios", "friki", "musica", "politica", "salud", "trabajo", "vecinos"];

        return $this->process($url, $feed, $categories, $action);
    }

    // Ahorrador o Rata
    public function aor($action = NULL){
        $url = 'http://www.ahorradororata.com/';
        $feed = 'http://feeds2.feedburner.com/ahorradororata';
        $categories = ["amistad", "amor", "casa", "comida_bebida", "ocio", "salud", "trabajo", "varios"];

        return $this->process($url, $feed, $categories, $action);
    }

    // Cuanta Razon
    public function cr($action = NULL){
        $url = 'http://www.cuantarazon.com/';
        $feed = 'http://feeds.feedburner.com/cuantarazon';
        $categories = ['ultimos'];
        $special = 'image';

        return $this->process($url, $feed, $categories, $action, $special);
    }

    // Cuanto Cabron
    public function cc($action = NULL){
        $url = 'http://www.cuantocabron.com/';
        $feed = 'http://feeds2.feedburner.com/cuantocabron';
        $categories = ['ultimos'];
        $special = 'image';

        return $this->process($url, $feed, $categories, $action, $special);
    }

    // Visto en Facebook
    public function vef($action = NULL){
        $url = 'http://www.vistoenlasredes.com/';
        $feed = 'http://feeds.feedburner.com/VistoEnFacebook';
        $categories = ["aplicaciones", "conversaciones", "estados", "famosos", "fotos", "fotos_de_portada", "grupos", "instagram",
                    "movil", "otros", "twitter", "videos", "yahoo_respuestas"];
        $special = 'image';

        return $this->process($url, $feed, $categories, $action, $special);
    }

    public function vg($action = NULL){
        $url = 'http://www.vayagif.com/';
        $feed = 'http://feeds.feedburner.com/vayagif';
        $categories = ['ultimos'];
        $special = 'video';

        return $this->process($url, $feed, $categories, $action, $special);
    }
}

?>
