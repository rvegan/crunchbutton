ALTER TABLE `pexcard_action` CHANGE `action` `action` enum('shift-started','shift-finished','order-accepted','order-cancelled','arbritary','remove-funds') DEFAULT NULL;