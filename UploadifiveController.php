<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadifiveController extends Controller
{
    private $checkToken = false;
    private $mustBeImage = false;
    private $uniqueServerFilename = true;
    private $uploadFolder = '/var/uploads';
    private $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png', 'pdf', 'doc', 'docx');

    /**
     * @Route("/upload", name="uploadifive")
     * @Method("POST")
     */
    public function uploadAction(Request $request)
    {
        // This approach assumes that the standard Symfony form token is used in uploadifive callback too
        if ($this->checkToken && !$this->isCsrfTokenValid('token_id', $request->request->get('token')))
        {
            return new Response("Token is invalid.");
        }
        elseif (!$request->files->count())
        {
            return new Response();
        }

        /** @var UploadedFile $file This action is called per file by UploadiFive */
        $file = $request->files->get('Filedata');

        if ($this->mustBeImage && !$this->isImage($file->getRealPath()))
        {
            return new Response("Error: File is not an image");
        }
        elseif (count($this->allowedExtensions) && !in_array(strtolower($file->getClientOriginalExtension()), $this->allowedExtensions))
        {
            return new Response("Error: File is not allowed");
        }

        if ($this->uniqueServerFilename)
        {
            $serverFilename = uniqid();
            $file->move($this->getFullUploadFolder(), $serverFilename);
            
            // return both unique server file name (of 13 characters) and original client name
            return new Response($serverFilename . $file->getClientOriginalName());
        }
        else
        {
            return new Response($file->getClientOriginalName());
        }
    }

    /**
     * @Route("/uploadexists", name="uploadifive_checkexists")
     * @Method("POST")
     */
    public function checkexistsAction(Request $request)
    {
        if ($this->uniqueServerFilename)
        {
            return new Response("0"); // duhhh
        }

        $fullPath = $this->getFullUploadFolder() . $request->request->get('filename');

        if (file_exists($fullPath)) {
            return new Response("1");
        } else {
            return new Response("0");
        }
    }

    /**
     * @Route("/download/{id}", name="download")
     * @Method("GET")
     */
    public function downloadAction($id)
    {
        /** @var \AppBundle\Entity\AFile */
        $file = $this->getDoctrine()->getEntityManager()->find(\AppBundle\Entity\AFile::class, $id);

        return $this->file($this->getFullUploadFolder() . $file->getUniqueServerFilename(), $file->getOriginalClientFilename());
    }

    private function isImage($tempFile) {

        // Get the size of the image
        $size = getimagesize($tempFile);

        if (isset($size) && $size[0] && $size[1] && $size[0] *  $size[1] > 0) {
            return true;
        } else {
            return false;
        }

    }

    private function getFullUploadFolder()
    {
        return
            $this->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR .
            $this->uploadFolder . DIRECTORY_SEPARATOR;
    }
    
    // This can be used by other Controllers to process returned value of upload action
    public static function splitFilenames($filenamesString)
    {
        $filenames = explode(",", $filenamesString);
        $filenames = array_filter($filenames); // remove empty
        $filenames = array_unique($filenames);
        $filenames = array_map('trim', $filenames);

        $associativeFilenames = array();
        foreach ($filenames as $filename)
        {
            $associativeFilenames[substr($filename, 0, 13)] = substr($filename, 13);
        }

        return $associativeFilenames;
    }
}
