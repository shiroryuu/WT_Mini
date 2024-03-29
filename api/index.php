<?php
  require_once("DB.php");
  require_once('Mail.php');


  $db = new DB('localhost','root','','sambandh');

if($_SERVER['REQUEST_METHOD'] == "GET"){

  if ($_GET['url'] == "auth"){

  } else if ($_GET['url'] == "users"){
                $token = $_COOKIE['MID'];
                $user_id = $db->query('SELECT user_id FROM login_tokens WHERE token=:token', array(':token'=>sha1($token)))[0]['user_id'];
                $username = $db->query('SELECT username FROM users WHERE id=:uid', array(':uid'=>$user_id))[0]['username'];
                echo $username;

  } else if ($_GET['url'] == "comments" && isset($_GET['postid'])) {
                $output = "";
                $comments = $db->query('SELECT comments.comment, users.username FROM comments, users WHERE post_id = :postid AND comments.user_id = users.id', array(':postid'=>$_GET['postid']));
                $output .= "[";
                foreach($comments as $comment) {
                        $output .= "{";
                        $output .= '"Comment": "'.$comment['comment'].'",';
                        $output .= '"CommentedBy": "'.$comment['username'].'"';
                        $output .= "},";
                        //echo $comment['comment']." ~ ".$comment['username']."<hr />";
                }
                $output = substr($output, 0, strlen($output)-1);
                $output .= "]";
                echo $output;


  }else if ($_GET['url'] == "posts") {
             $token = $_COOKIE['MID'];
             $userid = $db->query('SELECT user_id FROM login_tokens WHERE token=:token', array(':token'=>sha1($token)))[0]['user_id'];
             $followingposts = $db->query('SELECT posts.id, posts.body, posts.posted_at, posts.postimg, posts.likes, users.`username` FROM users, posts, followers
             WHERE (posts.user_id = followers.user_id
             OR posts.user_id = :userid)
             AND users.id = posts.user_id
             AND follower_id = :userid
             ORDER BY posts.posted_at DESC;', array(':userid'=>$userid), array(':userid'=>$userid));
             //TODO change posts.likes DESC to posts.posted_at DESC
             $response = "[";
             foreach($followingposts as $post) {
                     $response .= "{";
                             $response .= '"PostId": '.$post['id'].', ';
                             $response .= '"PostBody": "'.$post['body'].'", ';
                             $response .= '"PostedBy": "'.$post['username'].'", ';
                             $response .= '"PostDate": "'.$post['posted_at'].'", ';
                             $response .= '"PostImage": "'.$post['postimg'].'",';
                             $response .= '"Likes": '.$post['likes'].'';
                     $response .= "},";
             }
             $response = substr($response, 0, strlen($response)-1);
             $response .= "]";
             http_response_code(200);
             echo $response;
           }

           else if ($_GET['url'] == "profileposts") {
                      $userid = $db->query('SELECT id FROM users WHERE username=:username', array(':username'=>$_GET['username']))[0]['id'];
                      $followingposts = $db->query('SELECT posts.id, posts.body, posts.posted_at,posts.postimg, posts.likes, users.`username` FROM users, posts
                      WHERE users.id = posts.user_id
                      AND users.id = :userid
                      ORDER BY posts.posted_at DESC;', array(':userid'=>$userid));
                      $response = "[";
                      foreach($followingposts as $post) {
                              $response .= "{";
                                      $response .= '"PostId": '.$post['id'].',';
                                      $response .= '"PostBody": "'.$post['body'].'",';
                                      $response .= '"PostedBy": "'.$post['username'].'",';
                                      $response .= '"PostDate": "'.$post['posted_at'].'",';
                                      $response .= '"PostImage": "'.$post['postimg'].'",';
                                      $response .= '"Likes": '.$post['likes'].'';
                              $response .= "},";
                      }
                      $response = substr($response, 0, strlen($response)-1);
                      $response .= "]";
                      http_response_code(200);
                      echo $response;
          }

  }
  else if($_SERVER['REQUEST_METHOD'] == "POST"){

    if ($_GET['url'] == "users") {
          $postBody = file_get_contents("php://input");
          $postBody = json_decode($postBody);
          $username = $postBody->username;
          $email = $postBody->email;
          $password = $postBody->password;
          if (!$db->query('SELECT username FROM users WHERE username=:username', array(':username'=>$username))) {
                  if (strlen($username) >= 3 && strlen($username) <= 32) {
                          if (preg_match('/[a-zA-Z0-9_]+/', $username)) {
                                  if (strlen($password) >= 6 && strlen($password) <= 60) {
                                  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                  if (!$db->query('SELECT email FROM users WHERE email=:email', array(':email'=>$email))) {
                                          $db->query('INSERT INTO users VALUES (\'\', :username, :password, :email, \'0\', \'\')', array(':username'=>$username, ':password'=>password_hash($password, PASSWORD_BCRYPT), ':email'=>$email));
                                          Mail::sendMail('Welcome to Sambandh Matrimony!', 'Your account has been created!', $email);
                                          echo '{ "Success": "User Created!" }';
                                          http_response_code(200);
                                  } else {
                                          echo '{ "Error": "Email in use!" }';
                                          http_response_code(409);
                                  }
                          } else {
                                  echo '{ "Error": "Invalid Email!" }';
                                  http_response_code(409);
                                  }
                          } else {
                                  echo '{ "Error": "Invalid Password!" }';
                                  http_response_code(409);
                          }
                          } else {
                                  echo '{ "Error": "Invalid Username!" }';
                                  http_response_code(409);
                          }
                  } else {
                          echo '{ "Error": "Invalid Username!" }';
                          http_response_code(409);
                  }
          } else {
                  echo '{ "Error": "User exists!" }';
                  http_response_code(409);
          }
  }else if($_GET['url'] == "auth") {
      $postBody = file_get_contents("php://input");
      $postBody = json_decode($postBody);
      $username = $postBody->username;
      $password = $postBody->password;
      if($db->query('SELECT username FROM users WHERE username=:username', array(':username'=>$username))) {
        if(password_verify($password, $db->query('SELECT password FROM users WHERE username = :username',array(':username'=>$username))[0]['password'])){

            $cstrong = True;
            $token= bin2hex(openssl_random_pseudo_bytes(64,$cstrong));
            $user_id = $db->query('SELECT id FROM users WHERE username=:username', array(':username'=>$username))[0]['id'];
            $db->query('INSERT INTO login_tokens (token,user_id)VALUES (:token,:user_id)', array(':token'=>sha1($token),':user_id'=>$user_id));
            setcookie("MID",$token,time()+60*60*24*7*4,'/',NULL,NULL,TRUE);
            echo '{"token" : "'.$token.'"}';
        }

        else {
          http_response_code(401);
        }

      }
      else {
        http_response_code(401);
      }
    }

    else if ($_GET['url'] == "likes") {
                $postId = $_GET['id'];
                $token = $_COOKIE['MID'];
                $likedUserID = $db->query('SELECT user_id FROM login_tokens WHERE token=:token', array(':token'=>sha1($token)))[0]['user_id'];
                if (!$db->query('SELECT user_id FROM post_likes WHERE post_id=:postid AND user_id=:userid', array(':postid'=>$postId, ':userid'=>$likedUserID))) {
                        $db->query('UPDATE posts SET likes=likes+1 WHERE id=:postid', array(':postid'=>$postId));
                        $db->query('INSERT INTO post_likes VALUES (\'\', :postid, :userid)', array(':postid'=>$postId, ':userid'=>$likedUserID));

                } else {
                        $db->query('UPDATE posts SET likes=likes-1 WHERE id=:postid', array(':postid'=>$postId));
                        $db->query('DELETE FROM post_likes WHERE post_id=:postid AND user_id=:userid', array(':postid'=>$postId, ':userid'=>$likedUserID));
                }
                echo "{";
                echo '"Likes":';
                echo $db->query('SELECT likes FROM posts WHERE id=:postid', array(':postid'=>$postId))[0]['likes'];
                echo "}";
        }


        else if ($_GET['url'] == "changepass") {
          $postBody = file_get_contents("php://input");
          $postBody = json_decode($postBody);
          $oldPassword = $postBody->currentpassword;
          $newPassword = $postBody->newpassword;
          $confirmPassword = $PostBody->confirmpassword;

          

        }
        // else if ($_GET['url'] == "resetpass") {
        //   $postBody = file_get_contents("php://input");
        //   $postBody = json_decode($postBody);
        //   echo "$postBody->oldpassword";
        // }

  }

  else if($_SERVER['REQUEST_METHOD'] == "DELETE"){

  if($_GET['url']=="auth"){
    $token = $_COOKIE['MID'];
    echo "$token";
    if(isset($_COOKIE['MID'])){
      if($db->query('SELECT token FROM login_tokens WHERE token=:token',array(':token'=>sha1($_COOKIE['MID'])))){
          $userid = $db->query('SELECT user_id FROM login_tokens WHERE token=:token',array(':token'=>sha1($_COOKIE['MID'])))[0]['user_id'];
          if($_GET['check']==1){
            $db->query('DELETE FROM login_tokens WHERE user_id=:userid',array(':userid'=>$userid));
          }
          else {
            $db->query('DELETE FROM login_tokens WHERE token=:token', array(':token'=>sha1($_COOKIE['MID'])));
          }

          echo '{"status" : "success"}';
          http_response_code(200);
      }
      else {
        echo '{"Error" : "Invalid Token"}';
        http_response_code(400);
      }
    }
    else {
      echo '{"Error" : "Invalid Session"}';
      http_response_code(400);
    }
  }
}
else {
  http_response_code(405);
}


?>
