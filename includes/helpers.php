<?php

function get_site_domain()
{
    $home_url = home_url();
    $parsed_url = parse_url($home_url);
    return $parsed_url['host'];
}

