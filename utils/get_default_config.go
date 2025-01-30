package utils

func GetDefaultConfig() string {
	return `{
		"app_url": "http://localhost:5173",
		"base_storage_path": "storage",
		"bind_port": 9199,
		"jwt_secret": "you_must_change_this_or_you_will_be_hacked_you_have_been_warned",
		"max_share_size": "2G"
	}`
}
