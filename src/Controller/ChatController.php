<?php
/**
 * Created by PhpStorm.
 * User: rockwith
 * Date: 11/02/2018
 * Time: 19:06
 */

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends Controller
{
    const ROOM_TIMEOUT = 1209600;

    public static function generateRandomString($length = 8, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexNewChat()
    {
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/create", name="createChat", methods={"POST"})
     */
    public function createChat()
    {
        $chatToken = self::generateRandomString();
        $redis = $this->container->get('snc_redis.default');
        $redis->set('chat.room.' . $chatToken, 1, 'ex', self::ROOM_TIMEOUT);
        return $this->redirect($this->generateUrl('chatRoom', ['room' => urlencode($chatToken)]));
    }

    /**
     * @Route("/chat/{room}", name="chatRoom", methods={"GET"})
     */
    public function chatRoom($room)
    {
        $redis = $this->container->get('snc_redis.default');
        $exists = $redis->exists('chat.room.' . $room);
        if (! $exists){
            throw $this->createNotFoundException('The chat does not exist');
        }

        return $this->render('room.html.twig', ['room' => $room]);
    }
}