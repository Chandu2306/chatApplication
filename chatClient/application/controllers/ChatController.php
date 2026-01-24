<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ChatController extends CI_Controller {

    // private $api_url = "http://localhost:4000";    
  private $api_url;
public function __construct() {
    parent::__construct();
    $this->api_url = env('API_URL', 'http://localhost:4000');
}

    private function callApi($endpoint, $data = []) {
    $ch = curl_init($this->api_url . $endpoint);

    $headers = ["Content-Type: application/json"];

    if (isset($_COOKIE["token"])) {
        $headers[] = "Authorization: Bearer " . $_COOKIE["token"];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
    }
    public function index() {
        $this->load->view("register");
    }
    public function login() {
        $this->load->view("login");
    }

    public function registerSubmit() {
        $data = [
            "username" => $this->input->post("username"),
            "password" => $this->input->post("password")
        ];

        
        $response = $this->callApi("/register", $data);
    if($response && $response["success"]){
        redirect("chatcontroller/login");
    }else{
        $data["error"] = $response && $response["message"] ? $response["message"] : "connection issue";
        $this->load->view("register", $data);
    }

    }

    public function loginSubmit() {
        // echo "<script>console.log(`login submit called`);</script>";
        $data = [
            "username" => $this->input->post("username"),
            "password" => $this->input->post("password")
        ];

        $response = $this->callApi("/login", $data);

    if($response && $response["success"]){
            setcookie(
            "token",
            $response["token"],
            time() + 3600,
            "/",
            "",
            false,
            true
        );    
    redirect("chatcontroller/dashboard");
    }else{
        $data["error"] = $response && $response["message"] ? $response["message"] : "connection issue";
        $this->load->view("login", $data);
    }
    }

    public function dashboard() {
        if (!isset($_COOKIE["token"])) {
            redirect("chatcontroller/login");
            return;
        }

        $response = $this->callApi("/dashboard");

        $data = [
            "users" => $response["users"] ?? [],
            "currentUser" => $this->getUsernameFromToken()
        ];

        $this->load->view("dashboard", $data);
    }

    private function getUsernameFromToken() {
        if (!isset($_COOKIE["token"])) return null;

        $parts = explode('.', $_COOKIE["token"]);
        if (count($parts) !== 3) return null;

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        return $payload["username"] ?? null;
    }

    public function logout() {
        setcookie("token", "", time() - 3600, "/");
        redirect("chatcontroller/login");
    }

    

}
