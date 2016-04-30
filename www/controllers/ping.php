<?php

	if (!POST_REQUEST)
		CoreUtils::NotFound();

	CoreUtils::Respond(true);
