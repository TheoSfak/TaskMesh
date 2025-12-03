<?php
// TaskMesh - Simple WebSocket Server για Real-Time Chat
// Run με: php ws_server.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/lib/NotificationQueue.php';

$host = '0.0.0.0';
$port = 8080;

$clients = [];
$rooms = []; // team_id => [client1, client2, ...]

// Create WebSocket socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $port);
socket_listen($socket);
socket_set_nonblock($socket);

echo "🚀 TaskMesh WebSocket Server started on ws://$host:$port\n";
echo "📬 Notifications enabled via file queue\n";

while (true) {
    // Process notification queue first
    process_notification_queue();
    
    $read = array_merge([$socket], array_column($clients, 'socket'));
    $write = $except = null;
    
    if (socket_select($read, $write, $except, 0, 50000) < 1) {
        continue;
    }
    
    // New WebSocket connection
    if (in_array($socket, $read)) {
        $client = socket_accept($socket);
        socket_getpeername($client, $address);
        $client_id = spl_object_id($client);
        $clients[$client_id] = ['socket' => $client, 'handshake' => false, 'user_id' => null, 'rooms' => []];
        echo "New connection from $address (client_id: $client_id)\n";
        
        $key = array_search($socket, $read);
        unset($read[$key]);
    }
    
    // Handle client messages
    foreach ($read as $client_socket) {
        $client_id = spl_object_id($client_socket);
        
        if (!isset($clients[$client_id])) continue;
        
        $data = @socket_read($client_socket, 4096);
        
        if ($data === false || $data === '') {
            disconnect_client($client_id);
            continue;
        }
        
        // WebSocket handshake
        if (!$clients[$client_id]['handshake']) {
            perform_handshake($client_id, $data);
            continue;
        }
        
        // Decode message
        $message = decode_message($data);
        if ($message === false) continue;
        
        $payload = json_decode($message, true);
        if (!$payload) continue;
        
        handle_message($client_id, $payload);
    }
}

function perform_handshake($client_id, $data) {
    global $clients;
    
    if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $matches)) {
        $key = trim($matches[1]);
        $accept_key = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $accept_key\r\n\r\n";
        
        socket_write($clients[$client_id]['socket'], $response);
        $clients[$client_id]['handshake'] = true;
        
        // Extract token from request
        if (preg_match("/token=([^&\s]+)/", $data, $token_matches)) {
            $token = $token_matches[1];
            $decoded = JWT::decode($token);
            
            if ($decoded) {
                $clients[$client_id]['user_id'] = $decoded['user_id'];
                echo "User {$decoded['user_id']} authenticated\n";
            }
        }
    }
}

function decode_message($data) {
    $length = ord($data[1]) & 127;
    
    if ($length == 126) {
        $masks = substr($data, 4, 4);
        $data_part = substr($data, 8);
    } elseif ($length == 127) {
        $masks = substr($data, 10, 4);
        $data_part = substr($data, 14);
    } else {
        $masks = substr($data, 2, 4);
        $data_part = substr($data, 6);
    }
    
    $text = '';
    for ($i = 0; $i < strlen($data_part); $i++) {
        $text .= $data_part[$i] ^ $masks[$i % 4];
    }
    
    return $text;
}

function encode_message($text) {
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($text);
    
    if ($length <= 125) {
        $header = pack('CC', $b1, $length);
    } elseif ($length > 125 && $length < 65536) {
        $header = pack('CCn', $b1, 126, $length);
    } else {
        $header = pack('CCNN', $b1, 127, $length);
    }
    
    return $header . $text;
}

function handle_message($client_id, $payload) {
    global $clients, $rooms, $database, $db;
    
    $event = $payload['event'] ?? $payload['type'] ?? null;
    
    switch ($event) {
        case 'register':
            // Register user_id for this connection (for notifications)
            $user_id = $payload['user_id'] ?? null;
            if ($user_id) {
                $clients[$client_id]['user_id'] = $user_id;
                echo "Client $client_id registered as user $user_id\n";
            }
            break;
            
        case 'joinTeam':
            $team_id = $payload['team_id'] ?? null;
            if ($team_id) {
                if (!isset($rooms[$team_id])) {
                    $rooms[$team_id] = [];
                }
                $rooms[$team_id][] = $client_id;
                $clients[$client_id]['rooms'][] = $team_id;
                echo "Client $client_id joined team $team_id\n";
            }
            break;
            
        case 'leaveTeam':
            $team_id = $payload['team_id'] ?? null;
            if ($team_id && isset($rooms[$team_id])) {
                $rooms[$team_id] = array_diff($rooms[$team_id], [$client_id]);
                $clients[$client_id]['rooms'] = array_diff($clients[$client_id]['rooms'], [$team_id]);
                echo "Client $client_id left team $team_id\n";
            }
            break;
            
        case 'sendMessage':
            $team_id = $payload['team_id'] ?? null;
            $content = $payload['content'] ?? null;
            
            if ($team_id && $content && $clients[$client_id]['user_id']) {
                // Save to database
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "INSERT INTO messages (team_id, user_id, content) VALUES (:team_id, :user_id, :content)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":team_id", $team_id);
                $stmt->bindParam(":user_id", $clients[$client_id]['user_id']);
                $stmt->bindParam(":content", $content);
                $stmt->execute();
                
                $message_id = $db->lastInsertId();
                
                // Get user info
                $query = "SELECT u.first_name, u.last_name, u.avatar FROM users u WHERE u.id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $clients[$client_id]['user_id']);
                $stmt->execute();
                $user = $stmt->fetch();
                
                // Broadcast to room
                $response = [
                    'event' => 'newMessage',
                    'data' => [
                        'id' => $message_id,
                        'team_id' => $team_id,
                        'user_id' => $clients[$client_id]['user_id'],
                        'content' => $content,
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'avatar' => $user['avatar'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
                
                broadcast_to_room($team_id, $response);
            }
            break;
            
        case 'typing':
            $team_id = $payload['team_id'] ?? null;
            if ($team_id) {
                $response = ['event' => 'userTyping', 'data' => ['user_id' => $clients[$client_id]['user_id']]];
                broadcast_to_room($team_id, $response, $client_id);
            }
            break;
    }
}

function broadcast_to_room($team_id, $message, $exclude_client = null) {
    global $rooms, $clients;
    
    if (!isset($rooms[$team_id])) return;
    
    $encoded = encode_message(json_encode($message));
    
    foreach ($rooms[$team_id] as $client_id) {
        if ($client_id === $exclude_client) continue;
        if (isset($clients[$client_id])) {
            @socket_write($clients[$client_id]['socket'], $encoded);
        }
    }
}

function broadcast_to_user($user_id, $message) {
    global $clients;
    
    $encoded = encode_message(json_encode($message));
    
    foreach ($clients as $client_id => $client) {
        if (isset($client['user_id']) && $client['user_id'] == $user_id) {
            @socket_write($client['socket'], $encoded);
            echo "Sent notification to user $user_id (client $client_id)\n";
        }
    }
}

function process_notification_queue() {
    while ($item = NotificationQueue::pop()) {
        $user_id = $item['user_id'] ?? null;
        $notification = $item['notification'] ?? null;
        
        if ($user_id && $notification) {
            echo "🔔 Broadcasting queued notification to user $user_id\n";
            broadcast_to_user($user_id, [
                'type' => 'notification',
                'data' => $notification
            ]);
        }
    }
}

function disconnect_client($client_id) {
    global $clients, $rooms;
    
    echo "Client $client_id disconnected\n";
    
    // Remove from all rooms
    if (isset($clients[$client_id])) {
        foreach ($clients[$client_id]['rooms'] as $team_id) {
            if (isset($rooms[$team_id])) {
                $rooms[$team_id] = array_diff($rooms[$team_id], [$client_id]);
            }
        }
        
        socket_close($clients[$client_id]['socket']);
        unset($clients[$client_id]);
    }
}
?>