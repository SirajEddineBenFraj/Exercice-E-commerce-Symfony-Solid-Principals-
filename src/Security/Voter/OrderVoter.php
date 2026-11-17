<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Cart;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Answers one question: "may this user see this order?" Yes for its owner and for super admins.
 *
 * SOLID (Single Responsibility): the permission rule lives in one place, not copy-pasted in controllers.
 * SOLID (Open/Closed): a new rule (e.g. "support staff may see orders") = a new voter class,
 *   the controller's `denyAccessUnlessGranted(OrderVoter::VIEW, $order)` line stays as is.
 * SOLID (Liskov): Symfony asks every voter the same way; ours extends Voter and keeps its contract.
 *
 * @extends Voter<string, Cart>
 */
final class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Cart;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // decide() goes through the role hierarchy, unlike a plain in_array on getRoles().
        return $subject->getUser()->getId() === $user->getId()
            || $this->accessDecisionManager->decide($token, ['ROLE_SUPER_ADMIN']);
    }
}
