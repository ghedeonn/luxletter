<?php
declare(strict_types=1);
namespace In2code\Luxletter\Controller;

use In2code\Luxletter\Domain\Model\Newsletter;
use In2code\Luxletter\Domain\Model\User;
use In2code\Luxletter\Domain\Model\Usergroup;
use In2code\Luxletter\Domain\Repository\UsergroupRepository;
use In2code\Luxletter\Domain\Repository\UserRepository;
use In2code\Luxletter\Domain\Service\LogService;
use In2code\Luxletter\Domain\Service\ParseNewsletterUrlService;
use In2code\Luxletter\Exception\ArgumentMissingException;
use In2code\Luxletter\Exception\AuthenticationFailedException;
use In2code\Luxletter\Exception\MissingRelationException;
use In2code\Luxletter\Exception\UserValuesAreMissingException;
use In2code\Luxletter\Utility\BackendUserUtility;
use In2code\Luxletter\Utility\LocalizationUtility;
use In2code\Luxletter\Utility\ObjectUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;

/**
 * Class FrontendController
 */
class FrontendController extends ActionController
{
    /**
     * @var UserRepository
     */
    protected $userRepository = null;

    /**
     * @var UsergroupRepository
     */
    protected $usergroupRepository = null;

    /**
     * @var LogService
     */
    protected $logService = null;

    /**
     * @return void
     * @throws AuthenticationFailedException
     */
    public function initializePreviewAction(): void
    {
        if (BackendUserUtility::isBackendUserAuthenticated() === false) {
            throw new AuthenticationFailedException('You are not authenticated to see this view', 1560778826);
        }
    }

    /**
     * @param string $origin
     * @return string
     */
    public function previewAction(string $origin): string
    {
        try {
            $urlService = ObjectUtility::getObjectManager()->get(ParseNewsletterUrlService::class, $origin);
            return $urlService->getParsedContent();
        } catch (\Exception $exception) {
            return 'Origin ' . htmlspecialchars($origin) . ' could not be converted into a valid url!<br>'
                . 'Message: ' . $exception->getMessage();
        }
    }

    /**
     * Render a transparent gif and track the access as email-opening
     *
     * @param Newsletter|null $newsletter
     * @param User|null $user
     * @return string
     * @throws IllegalObjectTypeException
     * @throws Exception
     */
    public function trackingPixelAction(Newsletter $newsletter = null, User $user = null): string
    {
        if ($newsletter !== null && $user !== null) {
            $this->logService->logNewsletterOpening($newsletter, $user);
        }
        return base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
    }

    /**
     * @param User|null $user
     * @param Newsletter $newsletter
     * @param string $hash
     * @return void
     */
    public function unsubscribeAction(User $user = null, Newsletter $newsletter = null, string $hash = ''): void
    {
        try {
            $this->checkArgumentsForUnsubscribeAction($user, $newsletter, $hash);
            /** @var Usergroup $usergroupToRemove */
            $usergroupToRemove = $this->usergroupRepository->findByUid((int)$this->settings['removeusergroup']);
            $user->removeUsergroup($usergroupToRemove);
            $this->userRepository->update($user);
            $this->userRepository->persistAll();
            $this->view->assignMultiple([
                'success' => true,
                'user' => $user,
                'hash' => $hash,
                'usergroupToRemove' => $usergroupToRemove
            ]);
            if ($newsletter !== null) {
                $this->logService->logUnsubscribe($newsletter, $user);
            }
        } catch (\Exception $exception) {
            $languageKey = 'fe.unsubscribe.message.' . $exception->getCode();
            $message = LocalizationUtility::translate($languageKey);
            $this->addFlashMessage(($languageKey !== $message) ? $message : $exception->getMessage());
        }
    }

    /**
     * @param User|null $user
     * @param Newsletter|null $newsletter
     * @param string $hash
     * @return void
     * @throws ArgumentMissingException
     * @throws AuthenticationFailedException
     * @throws MissingRelationException
     * @throws UserValuesAreMissingException
     */
    protected function checkArgumentsForUnsubscribeAction(
        User $user = null,
        Newsletter $newsletter = null,
        string $hash = ''
    ): void {
        if ($user === null) {
            throw new ArgumentMissingException('User not given', 1562050511);
        }
        if ($newsletter === null) {
            throw new ArgumentMissingException('Newsletter not given', 1562267031);
        }
        if ($hash === '') {
            throw new ArgumentMissingException('Hash not given', 1562050533);
        }
        $usergroupToRemove = $this->usergroupRepository->findByUid((int)$this->settings['removeusergroup']);
        if ($user->getUsergroup()->contains($usergroupToRemove) === false) {
            throw new MissingRelationException('Usergroup not assigned to user', 1562066292);
        }
        if ($user->getUnsubscribeHash() !== $hash) {
            throw new AuthenticationFailedException('Given hash is incorrect', 1562069583);
        }
    }

    /**
     * @param UserRepository $userRepository
     * @return void
     */
    public function injectUserRepository(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param UsergroupRepository $usergroupRepository
     * @return void
     */
    public function injectUsergroupRepository(UsergroupRepository $usergroupRepository)
    {
        $this->usergroupRepository = $usergroupRepository;
    }

    /**
     * @param LogService $logService
     * @return void
     */
    public function injectLogService(LogService $logService)
    {
        $this->logService = $logService;
    }
}
