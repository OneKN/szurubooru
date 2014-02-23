<?php
class CommentModel extends AbstractCrudModel
{
	public static function getTableName()
	{
		return 'comment';
	}

	public static function spawn()
	{
		$comment = new CommentEntity;
		$comment->commentDate = time();
		return $comment;
	}

	public static function save($comment)
	{
		Database::transaction(function() use ($comment)
		{
			self::forgeId($comment);

			$bindings = [
				'text' => $comment->text,
				'post_id' => $comment->postId,
				'comment_date' => $comment->commentDate,
				'commenter_id' => $comment->commenterId];

			$stmt = new SqlUpdateStatement();
			$stmt->setTable('comment');
			$stmt->setCriterion(new SqlEqualsOperator('id', new SqlBinding($comment->id)));

			foreach ($bindings as $key => $val)
				$stmt->setColumn($key, new SqlBinding($val));

			Database::exec($stmt);
		});
	}

	public static function remove($comment)
	{
		Database::transaction(function() use ($comment)
		{
			$stmt = new SqlDeleteStatement();
			$stmt->setTable('comment');
			$stmt->setCriterion(new SqlEqualsOperator('id', new SqlBinding($comment->id)));
			Database::exec($stmt);
		});
	}



	public static function findAllByPostId($key)
	{
		$stmt = new SqlSelectStatement();
		$stmt->setColumn('comment.*');
		$stmt->setTable('comment');
		$stmt->setCriterion(new SqlEqualsOperator('post_id', new SqlBinding($key)));

		$rows = Database::fetchAll($stmt);
		if ($rows)
			return self::convertRows($rows);
		return [];
	}



	public static function preloadCommenters($comments)
	{
		self::preloadOneToMany($comments,
			function($comment) { return $comment->commenterId; },
			function($user) { return $user->id; },
			function($userIds) { return UserModel::findByIds($userIds); },
			function($comment, $user) { return $comment->setCache('commenter', $user); });
	}

	public static function preloadPosts($comments)
	{
		self::preloadOneToMany($comments,
			function($comment) { return $comment->postId; },
			function($post) { return $post->id; },
			function($postIds) { return PostModel::findByIds($postIds); },
			function($comment, $post) { $comment->setCache('post', $post); });
	}



	public static function validateText($text)
	{
		$text = trim($text);
		$config = \Chibi\Registry::getConfig();

		if (strlen($text) < $config->comments->minLength)
			throw new SimpleException(sprintf('Comment must have at least %d characters', $config->comments->minLength));

		if (strlen($text) > $config->comments->maxLength)
			throw new SimpleException(sprintf('Comment must have at most %d characters', $config->comments->maxLength));

		return $text;
	}
}