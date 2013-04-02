Wordpress Twitter oAuth
======================
Español
-------
Este plugin se construye debido al cambio sustancial de la API de twitter 1.1 donde deja de ser publica y requiere de nuestra autenticación. Para esto se ha desarrollado esta herramienta utilizando la libreria **twitteroauth** de [Abraham Williams](https://github.com/abraham/twitteroauth).

Para su funcionamiento es necesario que nos registremos como desarrolladores en [Twitter](http://dev.twitter.com) luego debemos crear una nueva aplicación con todos los datos requeridos para obtener las 2 claves necesarias para el funcionamiento de este plugin: CONSUMER KEY y CONSUMER SECRET

Una vez obtenidas estas claves es necesario ingresarlas en el archivo wordpress_twitter.php (linea 15 y 16) en donde son definidas como constantes.

Una vez activado veremos la opción en Apariencia > Conectar Twitter un boton que nos llevará a twitter para que autoricemos la aplicación creada anteriormente. Automáticamente volverá al admin de wordpress y nos enviará un mensaje en caso de aprobar o fallar la autentificación.

Por defecto el plugin guarda en un transient el objeto de los últimos 20 tweets del timeline autenticado, este transient se refresca cada 10 minutos, con esto impedimos que se consulte el api de manera reiterativa cuando no es necesario.

Para su utilización 
	<?php
		global $oauth_twitter;
		$twitter_timeline = $oauth_twitter->get_twitter_timeline();
		foreach ($twitter_timeline as $tweet):
			echo '<pre>'; print_r($tweet); echo '</pre>';
		endforeach;
	?>

o si solo deseamos obtener el último tweet parseado seria:
	<?php
		global $oauth_twitter;
		$last_tweet = $oauth_twitter->get_twitter_recent();
		echo '<pre>'; print_r($last_tweet); echo '</pre>';
	?>

English
-------
Plugin that connect twitter via Wordpress admin using *Twitteroauth Library* from Abraham Williams
