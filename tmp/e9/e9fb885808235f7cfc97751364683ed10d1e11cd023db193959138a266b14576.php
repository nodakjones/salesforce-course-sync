<?php

/* settings.twig */
class __TwigTemplate_885d23baf7369de1002e7babc1d09a5c1b3020c25ee1aa19e17131f61dbca213 extends Twig_Template
{
    private $source;

    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "<form method=\"post\" action=\"options.php\">
\t";
        // line 2
        echo twig_escape_filter($this->env, ($context["settingsFields"] ?? null), "html", null, true);
        echo "
  <input type=\"submit\" value=\"Save Settings\" />
</form>";
    }

    public function getTemplateName()
    {
        return "settings.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  26 => 2,  23 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "settings.twig", "/Users/dan/projects/ywam/uofn-wpengine/salesforce-course-sync/templates/settings.twig");
    }
}
