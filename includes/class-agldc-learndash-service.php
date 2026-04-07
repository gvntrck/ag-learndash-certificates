<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGLDC_LearnDash_Service {

	/**
	 * Checks if the LearnDash integration points used by the plugin are available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'learndash_user_get_enrolled_courses' )
			&& function_exists( 'learndash_get_course_steps' )
			&& function_exists( 'learndash_is_lesson_complete' );
	}

	/**
	 * Checks if LearnDash Groups functionality is available.
	 *
	 * @return bool
	 */
	public function groups_available() {
		return function_exists( 'learndash_get_course_groups' )
			&& function_exists( 'learndash_is_user_in_group' )
			&& function_exists( 'learndash_get_users_group_ids' );
	}

	/**
	 * Returns all groups associated with a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_course_groups( $course_id ) {
		if ( ! $this->groups_available() ) {
			return array();
		}

		$course_id   = absint( $course_id );
		$group_ids   = learndash_get_course_groups( $course_id );
		$result      = array();

		if ( empty( $group_ids ) || ! is_array( $group_ids ) ) {
			return $result;
		}

		foreach ( $group_ids as $group_id ) {
			$group_id = absint( $group_id );
			$group    = get_post( $group_id );

			if ( ! $group || 'publish' !== $group->post_status ) {
				continue;
			}

			$result[] = array(
				'group_id'    => $group_id,
				'group_title' => $group->post_title,
			);
		}

		return $result;
	}

	/**
	 * Returns all groups that the user belongs to.
	 *
	 * @param int $user_id User ID.
	 * @return array<int>
	 */
	public function get_user_group_ids( $user_id ) {
		if ( ! $this->groups_available() ) {
			return array();
		}

		$group_ids = learndash_get_users_group_ids( $user_id );

		return is_array( $group_ids ) ? array_map( 'absint', $group_ids ) : array();
	}

	/**
	 * Gets the user's group for a specific course.
	 * Returns the first group the user belongs to that is associated with the course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return int|null Group ID or null if not in any group for this course.
	 */
	public function get_user_course_group( $user_id, $course_id ) {
		if ( ! $this->groups_available() ) {
			return null;
		}

		$user_id     = absint( $user_id );
		$course_id   = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		$user_groups   = $this->get_user_group_ids( $user_id );
		$course_groups = $this->get_course_groups( $course_id );

		if ( empty( $user_groups ) || empty( $course_groups ) ) {
			return null;
		}

		$course_group_ids = array_map(
			function( $group ) {
				return $group['group_id'];
			},
			$course_groups
		);

		// Find intersection - user's groups that are also course groups
		$common_groups = array_intersect( $user_groups, $course_group_ids );

		if ( empty( $common_groups ) ) {
			return null;
		}

		// Return the first matching group
		return reset( $common_groups );
	}

	/**
	 * Returns all published courses.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_courses() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$args = array(
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$courses = get_posts( $args );
		$result  = array();

		foreach ( $courses as $course ) {
			$result[] = array(
				'course_id'    => $course->ID,
				'course_title' => $course->post_title,
			);
		}

		return $result;
	}

	/**
	 * Returns all enrolled courses with progress and eligibility information.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_user_course_progress( $user_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$course_ids = learndash_user_get_enrolled_courses( $user_id );

		if ( empty( $course_ids ) || ! is_array( $course_ids ) ) {
			return array();
		}

		$courses = array();

		foreach ( $course_ids as $course_id ) {
			$course_id = absint( $course_id );

			if ( ! $course_id || 'publish' !== get_post_status( $course_id ) ) {
				continue;
			}

			$stats = $this->get_course_progress_stats( $user_id, $course_id );

			if ( ! $stats ) {
				continue;
			}

			$courses[] = $stats;
		}

		return $courses;
	}

	/**
	 * Returns the detailed progress stats for a single course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return array<string, mixed>|null
	 */
	public function get_course_progress_stats( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $this->is_available() || ! $user_id || ! $course_id ) {
			return null;
		}

		$total_lessons = 0;
		$completed     = 0;
		$lesson_ids    = learndash_get_course_steps( $course_id, array( 'sfwd-lessons' ) );

		if ( is_array( $lesson_ids ) && ! empty( $lesson_ids ) ) {
			$total_lessons = count( $lesson_ids );

			foreach ( $lesson_ids as $lesson_id ) {
				if ( learndash_is_lesson_complete( $user_id, $lesson_id, $course_id ) ) {
					++$completed;
				}
			}
		} else {
			$total_lessons = $this->get_fallback_step_count( $course_id );
			$completed     = $this->get_fallback_completed_step_count( $user_id, $course_id );
		}

		$percentage = $total_lessons > 0 ? round( ( $completed / $total_lessons ) * 100, 2 ) : 0.0;

		return array(
			'course_id'      => $course_id,
			'course_title'   => get_the_title( $course_id ),
			'completed'      => $completed,
			'total'          => $total_lessons,
			'percentage'     => $percentage,
			'course_url'     => get_permalink( $course_id ),
			'is_completed'   => function_exists( 'learndash_course_completed' ) ? (bool) learndash_course_completed( $user_id, $course_id ) : false,
		);
	}

	/**
	 * Checks whether the user can generate the certificate for the course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @param float|int $threshold Threshold percentage.
	 * @return bool
	 */
	public function user_is_eligible_for_certificate( $user_id, $course_id, $threshold ) {
		$stats = $this->get_course_progress_stats( $user_id, $course_id );

		if ( ! $stats ) {
			return false;
		}

		if ( ! $this->user_has_course_access( $user_id, $course_id ) ) {
			return false;
		}

		if ( ! empty( $stats['is_completed'] ) ) {
			return true;
		}

		return (float) $stats['percentage'] >= (float) $threshold;
	}

	/**
	 * Checks whether the user still has access to the given course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public function user_has_course_access( $user_id, $course_id ) {
		if ( function_exists( 'sfwd_lms_has_access' ) ) {
			return (bool) sfwd_lms_has_access( $course_id, $user_id );
		}

		$course_ids = learndash_user_get_enrolled_courses( $user_id );

		return is_array( $course_ids ) && in_array( $course_id, array_map( 'absint', $course_ids ), true );
	}

	/**
	 * Uses LearnDash step counters when no lesson list is available.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private function get_fallback_step_count( $course_id ) {
		if ( function_exists( 'learndash_get_course_steps_count' ) ) {
			return (int) learndash_get_course_steps_count( $course_id );
		}

		if ( function_exists( 'learndash_course_get_steps_count' ) ) {
			return (int) learndash_course_get_steps_count( $course_id );
		}

		return 0;
	}

	/**
	 * Uses LearnDash progress helpers when the course does not expose lesson posts.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private function get_fallback_completed_step_count( $user_id, $course_id ) {
		if ( function_exists( 'learndash_user_get_course_progress' ) && function_exists( 'learndash_course_get_completed_steps' ) ) {
			$progress = learndash_user_get_course_progress( $user_id, $course_id );

			return (int) learndash_course_get_completed_steps( $user_id, $course_id, $progress );
		}

		return 0;
	}
}
