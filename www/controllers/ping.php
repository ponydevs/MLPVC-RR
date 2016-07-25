<?php

	if (!POST_REQUEST)
		CoreUtils::NotFound();

	Response::Done();
