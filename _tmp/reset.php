$hash = password_hash("Ml@2026gestor", PASSWORD_BCRYPT, ["cost" => 12]);
$upd = $GLOBALS["wpdb"]->update("wptools_gestor_usuarios", ["senha_hash" => $hash], ["email" => "mlopesdesign@gmail.com"]);
echo "Senha resetada, rows: " . $upd . PHP_EOL;
$del = $GLOBALS["wpdb"]->query("DELETE FROM wptools_options WHERE option_name LIKE '_transient_gestor_api_rl_%' OR option_name LIKE '_transient_timeout_gestor_api_rl_%'");
echo "Rate limit limpo, rows: " . $del . PHP_EOL;
